<?php

namespace Database\Seeders;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $acme = Tenant::create(['name' => 'Acme Procurement']);
        $northwind = Tenant::create(['name' => 'Northwind Sourcing']);
        $beta = Tenant::create(['name' => 'Beta Corp']);

        $requester = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'timezone' => 'Asia/Kuala_Lumpur',
            'locale' => 'en',
            'theme' => 'system',
        ]);
        $requester->tenants()->attach($acme->id, ['role' => TenantRole::Requester->value]);

        $buyer = User::factory()->create([
            'name' => 'Buyer User',
            'email' => 'buyer@example.com',
            'timezone' => 'UTC',
            'locale' => 'en',
            'theme' => 'light',
        ]);
        $buyer->tenants()->attach($northwind->id, ['role' => TenantRole::Buyer->value]);

        $approver = User::factory()->create([
            'name' => 'Approver User',
            'email' => 'approver@example.com',
            'timezone' => 'America/New_York',
            'locale' => 'en',
            'theme' => 'dark',
        ]);
        $approver->tenants()->attach($acme->id, ['role' => TenantRole::Approver->value]);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'timezone' => 'UTC',
            'locale' => 'en',
            'theme' => 'system',
        ]);
        $admin->tenants()->attach($acme->id, ['role' => TenantRole::Admin->value]);
        $admin->tenants()->attach($beta->id, ['role' => TenantRole::Admin->value]);
    }
}