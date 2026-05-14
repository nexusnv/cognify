<?php

namespace Database\Seeders\Demo;

use App\Tenancy\Tenant;

class DemoTenantSeeder
{
    public function run(DemoSeedContext $context): void
    {
        foreach ([
            'acme' => 'Acme Procurement',
            'northwind' => 'Northwind Sourcing',
            'beta' => 'Beta Corp Sandbox',
        ] as $key => $name) {
            $context->tenants->put(
                $key,
                Tenant::query()->updateOrCreate(
                    ['name' => $name],
                    ['name' => $name],
                ),
            );
        }
    }
}
