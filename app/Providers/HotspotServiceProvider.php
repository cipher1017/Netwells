<?php

namespace App\Providers;

use App\Contracts\AccessProvisioner;
use App\Contracts\PaymentGateway;
use App\Payments\FreeGateway;
use App\Services\Router\RouterOsApiProvisioner;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class HotspotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Access enforcement: RouterOS today, RADIUS later — swap here only.
        $this->app->bind(AccessProvisioner::class, RouterOsApiProvisioner::class);

        // Payment gateway chosen by config('hotspot.payment_mode').
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('hotspot.payment_mode')) {
                'free'  => $this->app->make(FreeGateway::class),
                // 'mpesa' => $this->app->make(DarajaSandboxGateway::class), // next milestone
                default => throw new RuntimeException(
                    'Unsupported PAYMENT_MODE: ' . config('hotspot.payment_mode')
                ),
            };
        });
    }
}
