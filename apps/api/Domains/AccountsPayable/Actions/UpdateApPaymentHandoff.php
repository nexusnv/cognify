<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ApPaymentHandoff $handoff, User $actor, array $data): ApPaymentHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $data): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Draft) {
                throw new ConflictHttpException('Only draft AP payment handoffs can be updated.');
            }

            $handoff->assertLockVersion((int) Arr::get($data, 'lockVersion'));
            $before = $handoff->only(['notes', 'effective_payment_date', 'remittance_reference', 'lock_version']);

            $attributes = [
                'lock_version' => $handoff->lock_version + 1,
            ];

            $optionalFields = [
                'notes' => 'notes',
                'effectivePaymentDate' => 'effective_payment_date',
                'remittanceReference' => 'remittance_reference',
            ];

            foreach ($optionalFields as $inputKey => $column) {
                if (Arr::exists($data, $inputKey)) {
                    $attributes[$column] = $data[$inputKey];
                }
            }

            $handoff->forceFill($attributes)->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.updated',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['notes', 'effective_payment_date', 'remittance_reference', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}
