<?php

namespace Domains\Approval\Jobs;

use App\Tenancy\Tenant;
use Domains\Approval\Actions\EscalateOverdueApprovalTasks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EscalateOverdueApprovalTasksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(EscalateOverdueApprovalTasks $action): void
    {
        Tenant::query()
            ->orderBy('id')
            ->chunkById(200, function ($tenants) use ($action): void {
                foreach ($tenants as $tenant) {
                    $action->handle($tenant);
                }
            });
    }
}
