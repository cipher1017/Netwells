<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Router;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class NetwellsSeeder extends Seeder
{
    /**
     * Seed the first tenant (Netwells Technologies), its router (hs-site1)
     * and its two launch plans. Idempotent: safe to run repeatedly.
     */
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'netwells'],
            [
                'name'       => 'Netwells Technologies',
                'brand_name' => 'Netwells Technologies',
            ]
        );

        Router::updateOrCreate(
            ['identity' => 'hs-site1'],
            [
                'tenant_id'      => $tenant->id,
                'host'           => config('hotspot.router.host'),
                'api_port'       => config('hotspot.router.api_port'),
                'api_user'       => config('hotspot.router.api_user'),
                'api_password'   => config('hotspot.router.api_password'),
                'hotspot_server' => config('hotspot.router.hotspot_server'),
            ]
        );

        $plans = [
            [
                'name'             => '1 Hour',
                'duration_minutes' => 60,
                'price_kes'        => 10,
                'rate_limit'       => '3M/3M',
                'router_profile'   => 'plan-1h',
                'sort_order'       => 1,
            ],
            [
                'name'             => '24 Hours',
                'duration_minutes' => 1440,
                'price_kes'        => 50,
                'rate_limit'       => '3M/3M',
                'router_profile'   => 'plan-24h',
                'sort_order'       => 2,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['tenant_id' => $tenant->id, 'router_profile' => $plan['router_profile']],
                array_merge($plan, ['tenant_id' => $tenant->id, 'active' => true]),
            );
        }
    }
}
