<?php

namespace Tests\Feature;

use Database\Seeders\NetwellsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NetwellsSeeder::class);
    }

    private array $handoff = [
        'mac'    => 'AA-BB-CC-DD-EE-FF',
        'ip'     => '192.168.88.77',
        'router' => 'hs-site1',
        'login'  => 'http://login.wifi/login',
        'dst'    => 'http://example.com',
    ];

    public function test_landing_with_valid_params_shows_plans_and_brand(): void
    {
        $response = $this->get('/?' . http_build_query($this->handoff));

        $response->assertOk();
        $response->assertSee('Netwells Technologies');
        $response->assertSee('1 Hour');
        $response->assertSee('24 Hours');
        $response->assertSee('FREE', false);
    }

    public function test_landing_stores_normalised_context_in_session(): void
    {
        $this->get('/?' . http_build_query($this->handoff));

        $portal = session('portal');

        $this->assertIsArray($portal);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $portal['mac']); // normalised
        $this->assertSame('hs-site1', $portal['router_identity']);
        $this->assertSame('http://login.wifi/login', $portal['login']);
        $this->assertSame('http://example.com', $portal['dst']);
        $this->assertNotNull($portal['router_id']);
        $this->assertNotNull($portal['tenant_id']);
    }

    public function test_landing_without_params_shows_connect_screen(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Please connect to');
    }

    public function test_landing_with_missing_mac_shows_connect_screen(): void
    {
        $this->get('/?router=hs-site1')
            ->assertOk()
            ->assertSee('Please connect to');
    }

    public function test_landing_with_unknown_router_shows_connect_screen(): void
    {
        $this->get('/?mac=AA-BB-CC-DD-EE-FF&router=does-not-exist')
            ->assertOk()
            ->assertSee('Please connect to');
    }

    public function test_landing_refresh_without_params_keeps_captured_context(): void
    {
        // First arrival captures context.
        $this->get('/?' . http_build_query($this->handoff))->assertSee('Tap to connect');

        // A bare refresh (captive popups re-hit "/") must keep showing plans.
        $this->get('/')
            ->assertOk()
            ->assertSee('Tap to connect')
            ->assertDontSee('Please connect to');
    }

    public function test_landing_accepts_already_colon_separated_mac(): void
    {
        $this->get('/?mac=aa:bb:cc:dd:ee:ff&router=hs-site1');

        $this->assertSame('AA:BB:CC:DD:EE:FF', session('portal')['mac']);
    }
}
