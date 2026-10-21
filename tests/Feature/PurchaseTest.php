<?php

namespace Tests\Feature;

use App\Contracts\AccessProvisioner;
use App\Models\Device;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ActivateOrder;
use Database\Seeders\NetwellsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAccessProvisioner;
use Tests\TestCase;

class PurchaseTest extends TestCase
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

    private array $handoff = [
        'mac'    => 'AA-BB-CC-DD-EE-FF',
        'ip'     => '192.168.88.77',
        'router' => 'hs-site1',
        'login'  => 'http://login.wifi/login',
        'dst'    => 'http://example.com',
    ];

    private function plan(string $name): Plan
    {
        return Plan::where('name', $name)->firstOrFail();
    }

    public function test_free_purchase_activates_subscription_and_shows_autologin(): void
    {
        $this->get('/?' . http_build_query($this->handoff))->assertOk();

        $response = $this->post('/purchase', ['plan_id' => $this->plan('1 Hour')->id]);

        $response->assertOk();
        $response->assertSee('all set', false);
        $response->assertSee('AA:BB:CC:DD:EE:FF'); // hotspot username (the MAC)
        $response->assertSee('http://login.wifi/login', false); // auto-login posts to router

        $order = Order::firstOrFail();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->activated_at);

        $sub = Subscription::firstOrFail();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $sub->hotspot_username);
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($sub->expires_at), 1);

        $this->assertSame(1, $this->provisioner->grantCount());
    }

    public function test_second_purchase_extends_active_subscription(): void
    {
        $this->get('/?' . http_build_query($this->handoff));
        $this->post('/purchase', ['plan_id' => $this->plan('1 Hour')->id])->assertOk();

        $first = Subscription::firstOrFail();
        $firstExpiry = $first->expires_at->copy();

        // Buy a 24h plan while still active -> extend, not a new subscription.
        $this->post('/purchase', ['plan_id' => $this->plan('24 Hours')->id])->assertOk();

        $this->assertSame(1, Subscription::count(), 'Extend must not create a second subscription');

        $sub = Subscription::firstOrFail();
        $this->assertEqualsWithDelta(
            $firstExpiry->addMinutes(1440)->timestamp,
            $sub->expires_at->timestamp,
            2,
            'expires_at should be previous expiry + 24h',
        );

        $this->assertSame(2, $this->provisioner->grantCount()); // re-granted on extend
    }

    public function test_activate_order_is_idempotent(): void
    {
        $this->get('/?' . http_build_query($this->handoff));
        $this->post('/purchase', ['plan_id' => $this->plan('1 Hour')->id])->assertOk();

        $order = Order::firstOrFail();
        $grantsAfterPurchase = $this->provisioner->grantCount();

        // Re-activating the same order must not duplicate or re-grant.
        app(ActivateOrder::class)->activate($order->fresh());

        $this->assertSame(1, Subscription::count());
        $this->assertSame($grantsAfterPurchase, $this->provisioner->grantCount());
    }

    public function test_free_cooldown_blocks_new_session_without_active_subscription(): void
    {
        $this->get('/?' . http_build_query($this->handoff));
        $this->post('/purchase', ['plan_id' => $this->plan('1 Hour')->id])->assertOk();

        // Force the subscription to expire so there is no active session.
        Subscription::query()->update([
            'status'     => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->post('/purchase', ['plan_id' => $this->plan('1 Hour')->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Only the first order was activated; the second was blocked pre-order.
        $this->assertSame(1, Order::where('status', Order::STATUS_PAID)->count());
        $this->assertSame(1, Subscription::count());
    }

    public function test_purchase_without_context_redirects_to_landing(): void
    {
        // No prior GET / to seed session context.
        $this->post('/purchase', ['plan_id' => $this->plan('1 Hour')->id])
            ->assertRedirect(route('portal.landing'));

        $this->assertSame(0, Order::count());
    }
}
