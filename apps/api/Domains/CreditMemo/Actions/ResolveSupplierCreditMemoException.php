<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResolveSupplierCreditMemoException
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        SupplierCreditMemoException $exception,
        User $actor,
        int $lockVersion,
        string $resolutionType,
        ?string $resolutionNotes,
    ): SupplierCreditMemoException {
        $validResolution = null;
        foreach (SupplierCreditMemoExceptionResolutionType::cases() as $case) {
            if ($case->value === $resolutionType) {
                $validResolution = $case;
                break;
            }
        }

        if ($validResolution === null) {
            throw ValidationException::withMessages([
                'resolutionType' => sprintf('Invalid resolution type %s.', $resolutionType),
            ]);
        }

        return DB::transaction(function () use ($exception, $actor, $lockVersion, $validResolution, $resolutionNotes): SupplierCreditMemoException {
            $lockedException = SupplierCreditMemoException::query()
                ->whereKey($exception->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedException->assertLockVersion($lockVersion);

            if ($lockedException->resolved_at !== null) {
                throw new ConflictHttpException('Exception is already resolved.');
            }

            $lockedException->forceFill([
                'resolution_type' => $validResolution->value,
                'resolution_notes' => $resolutionNotes,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'lock_version' => (int) $lockedException->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $lockedException->tenant,
                actor: $actor,
                action: 'supplier_credit_memo_exception.resolved',
                subject: $lockedException,
                metadata: [
                    'creditMemoId' => (string) $lockedException->supplier_credit_memo_id,
                    'exceptionType' => (string) $lockedException->exception_type,
                    'resolutionType' => $validResolution->value,
                ],
            ));

            return $lockedException->fresh();
        });
    }
}
