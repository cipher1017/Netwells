<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment mode
    |--------------------------------------------------------------------------
    | Selects which PaymentGateway implementation is bound in the container.
    |   "free"  => FreeGateway (marks order paid immediately)
    |   "mpesa" => DarajaSandboxGateway (added next milestone)
    */
    'payment_mode' => env('PAYMENT_MODE', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Free-plan abuse guard
    |--------------------------------------------------------------------------
    | A device (MAC) may place at most one free order within this many hours.
    */
    'free_cooldown_hours' => (int) env('FREE_COOLDOWN_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Default router connection (seeded as tenant router "hs-site1")
    |--------------------------------------------------------------------------
    | The seeder reads these to create the first router row. At runtime the
    | provisioner uses the per-router values stored in the database, so a
    | tenant can have many routers with different credentials.
    */
    'router' => [
        'host'           => env('ROUTER_HOST', '192.168.88.1'),
        'api_port'       => (int) env('ROUTER_API_PORT', 8728),
        'api_user'       => env('ROUTER_API_USER', 'billing-api'),
        'api_password'   => env('ROUTER_API_PASSWORD', ''),
        'hotspot_server' => env('ROUTER_HOTSPOT_SERVER', 'hotspot1'),
    ],

];
