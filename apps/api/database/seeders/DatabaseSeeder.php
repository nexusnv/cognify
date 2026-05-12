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
    }
}
