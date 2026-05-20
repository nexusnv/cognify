<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateRfqInvitations
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param array<string, mixed> $data
     * @return array<int, RfqInvitation>
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): array
    {
        Gate::forUser($actor)->authorize('create', [RfqInvitation::class, $rfq]);

        try {
            return DB::transaction(function () use ($tenant, $actor, $rfq, $data): array {
                $lockedRfq = Rfq::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($rfq->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedRfq->isEditable()) {
                    throw new ConflictHttpException('This RFQ cannot receive invitations.');
                }

                $vendorIds = collect($data['vendorIds'])
                    ->map(static fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                $vendors = Vendor::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->whereIn('id', $vendorIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($vendors->count() !== $vendorIds->count()) {
                    throw ValidationException::withMessages([
                        'vendorIds' => ['All vendors must be active vendors in the current tenant.'],
                    ]);
                }

                $existing = RfqInvitation::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('rfq_id', $lockedRfq->id)
                    ->whereIn('vendor_id', $vendorIds)
                    ->lockForUpdate()
                    ->exists();

                if ($existing) {
                    throw new ConflictHttpException('An invitation already exists for one or more selected vendors.');
                }

                $overrides = collect($data['contactOverrides'] ?? [])->keyBy(static fn (array $item): int => (int) $item['vendorId']);

                return $vendorIds->map(function (int $vendorId) use ($tenant, $actor, $lockedRfq, $vendors, $overrides, $data): RfqInvitation {
                    $vendor = $vendors->get($vendorId);
                    $override = $overrides->get($vendorId, []);

                    $invitation = RfqInvitation::query()->create([
                        'tenant_id' => $tenant->id,
                        'rfq_id' => $lockedRfq->id,
                        'vendor_id' => $vendor->id,
                        'status' => RfqInvitationStatus::Sent->value,
                        'contact_name' => array_key_exists('contactName', $override) ? $override['contactName'] : data_get($vendor->metadata, 'contactName'),
                        'contact_email' => array_key_exists('contactEmail', $override) ? $override['contactEmail'] : data_get($vendor->metadata, 'contactEmail'),
                        'message' => array_key_exists('message', $data) ? $data['message'] : null,
                        'response_due_at' => array_key_exists('responseDueAt', $data) ? $data['responseDueAt'] : null,
                        'sent_at' => now(),
                    ]);

                    foreach (['rfq_invitation.created', 'rfq_invitation.sent'] as $action) {
                        $this->audit->record(new AuditEventData(
                            tenant: $tenant,
                            actor: $actor,
                            action: $action,
                            subject: $invitation,
                            metadata: [
                                'rfqId' => (string) $lockedRfq->id,
                                'vendorId' => (string) $vendor->id,
                            ],
                            subjectDisplay: $vendor->name,
                        ));
                    }

                    return $invitation->refresh()->load('vendor');
                })->all();
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueInvitationViolation($exception)) {
                throw $exception;
            }

            throw new ConflictHttpException('An invitation already exists for one or more selected vendors.', $exception);
        }
    }

    private function isUniqueInvitationViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rfq_invitation_tenant_rfq_vendor_unique')
            || (str_contains($message, 'unique') && str_contains($message, 'rfq_invitations'));
    }
}
