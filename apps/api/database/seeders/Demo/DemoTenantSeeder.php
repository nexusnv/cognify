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
        ] as $slug => $name) {
            $context->tenants->put(
                $slug,
                Tenant::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name],
                ),
            );
        }
    }
}
