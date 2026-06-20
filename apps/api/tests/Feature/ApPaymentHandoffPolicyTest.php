<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\Policies\ApPaymentHandoffPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApPaymentHandoffPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_export_abilities_require_buyer_or_admin(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $handoff = $this->createHandoff($tenant);
        app(CurrentTenant::class)->set($tenant);

        $policy = app(ApPaymentHandoffPolicy::class);
        $this->assertTrue($policy->schedule($buyer, $handoff));
        $this->assertTrue($policy->addAllocation($buyer, $handoff));
        $this->assertTrue($policy->markPaid($buyer, $handoff));
        $this->assertTrue($policy->closeWithVariance($buyer, $handoff));
        $this->assertTrue($policy->markFailed($buyer, $handoff));
        $this->assertTrue($policy->void($buyer, $handoff));
        $this->assertTrue($policy->reschedule($buyer, $handoff));
    }

    public function test_post_export_abilities_reject_requester(): void
    {
        [$tenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);
        $handoff = $this->createHandoff($tenant);
        app(CurrentTenant::class)->set($tenant);

        $policy = app(ApPaymentHandoffPolicy::class);
        $this->assertFalse($policy->schedule($requester, $handoff));
        $this->assertFalse($policy->markPaid($requester, $handoff));
        $this->assertFalse($policy->void($requester, $handoff));
    }

    public function test_post_export_abilities_reject_cross_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $handoff = $this->createHandoff($tenant);
        app(CurrentTenant::class)->set($tenant);

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        app(CurrentTenant::class)->set($otherTenant);

        $policy = app(ApPaymentHandoffPolicy::class);
        $this->assertFalse($policy->markPaid($otherBuyer, $handoff));
    }

    private function createHandoff(Tenant $tenant): ApPaymentHandoff
    {
        return ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'APH-POL-'.Str::random(6),
            'status' => 'scheduled',
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'lock_version' => 1,
        ]);
    }

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }
}
