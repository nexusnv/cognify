<?php

namespace Domains\Approval\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ApprovalSlaCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $slaRules
     */
    public function calculateDueAtForStage(array $slaRules, string $stageName, ?CarbonInterface $baseline = null): ?Carbon
    {
        foreach ($slaRules as $slaRule) {
            if ((string) ($slaRule['stage'] ?? '') !== $stageName) {
                continue;
            }

            return $this->calculateDueAt($slaRule, $baseline);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $slaRule
     */
    public function calculateDueAt(array $slaRule, ?CarbonInterface $baseline = null): ?Carbon
    {
        $baseline ??= now();

        if (array_key_exists('durationMinutes', $slaRule) && is_numeric($slaRule['durationMinutes'])) {
            return Carbon::instance($baseline)->copy()->addMinutes((int) $slaRule['durationMinutes']);
        }

        if (array_key_exists('dueInMinutes', $slaRule) && is_numeric($slaRule['dueInMinutes'])) {
            return Carbon::instance($baseline)->copy()->addMinutes((int) $slaRule['dueInMinutes']);
        }

        if (array_key_exists('dueInHours', $slaRule) && is_numeric($slaRule['dueInHours'])) {
            return Carbon::instance($baseline)->copy()->addHours((int) $slaRule['dueInHours']);
        }

        return null;
    }
}
