<?php

namespace Tests\Feature;

use App\Contracts\AccessProvisioner;
use App\Models\Device;
use App\Models\Order;
use App\Models\Subscription;
use Database\Seeders\NetwellsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAccessProvisioner;
use Tests\TestCase;

class ExpiryTest extends TestCase
{
    use RefreshDatabase;

    private FakeAccessProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NetwellsSeeder::class);

        $this->provisioner = new FakeAccessProvisioner();
        $this->app->instance(AccessProvisioner::class, $this->provisioner);
    }

    private function makeSubscription(string $status, \DateTimeInterface $expiresAt): Subscription
    {
        $tenantId = \App\Models\Tenant::value('id');
        $router = \App\Models\Router::firstOrFail();
        $plan = \App\Models\Plan::where('name', '1 Hour')->firstOrFail();

        $device = Device::create([
            'tenant_id'    => $tenantId,
            'mac'          => '11:22:33:44:55:66',
            'last_seen_at' => now(),
        ]);

        $order = Order::create([
            'tenant_id'      => $tenantId,
            'router_id'      => $router->id,
            'device_id'      => $device->id,
            'plan_id'        => $plan->id,
            'amount_kes'     => $plan->price_kes,
            'payment_method' => Order::METHOD_FREE,
            'status'         => Order::STATUS_PAID,
            'paid_at'        => now(),
            'activated_at'   => now(),
        ]);

        return Subscription::create([
            'tenant_id'        => $tenantId,
            'order_id'         => $order->id,
            'device_id'        => $device->id,
            'router_id'        => $router->id,
            'hotspot_username' => $device->mac,
            'hotspot_password' => 'secret12',
            'starts_at'        => now()->subHour(),
            'expires_at'       => $expiresAt,
            'status'           => $status,
        ]);
    }

    public function test_expire_command_revokes_and_expires_due_subscriptions(): void
    {
        $due = $this->makeSubscription(Subscription::STATUS_ACTIVE, now()->subMinute());

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(Subscription::STATUS_EXPIRED, $due->fresh()->status);
        $this->assertSame(1, $this->provisioner->revokeCount());
        $this->assertSame('11:22:33:44:55:66', $this->provisioner->revoked[0]['username']);
    }

    public function test_expire_command_leaves_future_subscriptions_active(): void
    {
        $future = $this->makeSubscription(Subscription::STATUS_ACTIVE, now()->addHour());

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(Subscription::STATUS_ACTIVE, $future->fresh()->status);
        $this->assertSame(0, $this->provisioner->revokeCount());
    }

    public function test_status_page_shows_active_plan_and_time_remaining(): void
    {
        $sub = $this->makeSubscription(Subscription::STATUS_ACTIVE, now()->addMinutes(45));

        $this->withSession(['portal' => [
            'mac'       => '11:22:33:44:55:66',
            'tenant_id' => $sub->tenant_id,
        ]]);

        $response = $this->get('/status');

        $response->assertOk();
        $response->assertSee('connected', false);
        $response->assertSee('1 Hour');
        $response->assertSee($sub->expires_at->format('g:i A'));
    }

    public function test_status_page_without_active_subscription(): void
    {
        $this->withSession(['portal' => ['mac' => 'AA:AA:AA:AA:AA:AA', 'tenant_id' => 1]]);

        $this->get('/status')
            ->assertOk()
            ->assertSee('No active plan');
    }
}
