<?php

namespace Database\Seeders\Demo;

use App\Auth\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $users = [
            'requester' => ['Test User', 'test@example.com', TenantRole::Requester->value, ['acme']],
            'buyer' => ['Buyer User', 'buyer@example.com', TenantRole::Buyer->value, ['acme']],
            'approver' => ['Approver User', 'approver@example.com', TenantRole::Approver->value, ['acme']],
            'finance' => ['Finance User', 'finance@example.com', TenantRole::Approver->value, ['acme']],
            'auditor' => ['Audit User', 'auditor@example.com', TenantRole::Admin->value, ['acme']],
            'vendor_manager' => ['Vendor Manager', 'vendor.manager@example.com', TenantRole::Buyer->value, ['northwind']],
            'admin' => ['Admin User', 'admin@example.com', TenantRole::Admin->value, ['acme', 'beta']],
        ];

        foreach ($users as $key => [$name, $email, $role, $tenantKeys]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'locale' => 'en',
                    'theme' => 'system',
                ],
            );

            foreach ($tenantKeys as $tenantKey) {
                $tenant = $context->tenants->get($tenantKey);

                $user->tenants()->syncWithoutDetaching([
                    $tenant->id => ['role' => $role],
                ]);
                $user->tenants()->updateExistingPivot($tenant->id, ['role' => $role]);
            }

            $context->users->put($key, $user->refresh());
        }
    }
}
