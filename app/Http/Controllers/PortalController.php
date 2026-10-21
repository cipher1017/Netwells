<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Device;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\ActivateOrder;
use App\Support\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    /**
     * GET / — captive-portal landing.
     *
     * Reads the router handoff parameters, validates them, stores the context
     * in the session, then shows the branded plan list. If we cannot identify
     * the phone (no MAC) or its router, we show a friendly "connect" screen.
     */
    public function landing(Request $request): View
    {
        $context = PortalContext::fromRequest($request);

        // Only overwrite the stored context when the router actually handed us
        // fresh parameters; a plain refresh of "/" (no query string) should
        // keep whatever we captured on the first redirect.
        if ($request->query('mac') !== null || $request->query('router') !== null) {
            $context->store($request);
        } else {
            $context = PortalContext::fromSession($request) ?? $context;
        }

        if (! $context->isUsable()) {
            return view('portal.connect', [
                'brandName' => $this->defaultBrandName(),
            ]);
        }

        $tenant = Tenant::find($context->tenantId);

        $plans = Plan::where('tenant_id', $context->tenantId)
            ->active()
            ->orderBy('sort_order')
            ->get();

        return view('portal.landing', [
            'brandName' => $tenant?->brand_name ?? $this->defaultBrandName(),
            'plans'     => $plans,
            'context'   => $context,
            'freeMode'  => config('hotspot.payment_mode') === 'free',
        ]);
    }

    /**
     * POST /purchase — upsert the device, create an order, run the configured
     * PaymentGateway, and on payment activate the subscription and show the
     * auto-login page.
     */
    public function purchase(Request $request, PaymentGateway $gateway, ActivateOrder $activator): RedirectResponse|View
    {
        $request->validate(['plan_id' => ['required', 'integer']]);

        $context = PortalContext::fromSession($request);

        if (! $context || ! $context->isUsable()) {
            return redirect()->route('portal.landing');
        }

        $plan = Plan::where('tenant_id', $context->tenantId)
            ->active()
            ->find($request->integer('plan_id'));

        if (! $plan) {
            return back()->with('error', 'That plan is no longer available. Please pick another.');
        }

        $device = Device::updateOrCreate(
            ['tenant_id' => $context->tenantId, 'mac' => $context->mac],
            ['last_ip' => $context->ip, 'last_seen_at' => now()],
        );

        $hasActive = Subscription::where('device_id', $device->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->exists();

        // Free-mode abuse guard: only gate brand-new sessions. A device with a
        // live subscription is allowed to extend (that is the intended flow).
        if (config('hotspot.payment_mode') === 'free' && ! $hasActive) {
            if ($wait = $this->freeCooldownRemaining($device)) {
                return back()->with('error', "You've recently used a free session. Please try again in {$wait}.");
            }
        }

        $order = Order::create([
            'tenant_id'      => $context->tenantId,
            'router_id'      => $context->routerId,
            'device_id'      => $device->id,
            'plan_id'        => $plan->id,
            'amount_kes'     => $plan->price_kes,
            'payment_method' => Order::METHOD_FREE,
            'status'         => Order::STATUS_PENDING,
        ]);

        $result = $gateway->initiate($order);

        if ($result->isFailed()) {
            return back()->with('error', $result->message ?? 'Payment failed. Please try again.');
        }

        if ($result->isPending()) {
            // Reserved for M-Pesa STK (next milestone). Not reachable in free mode.
            return back()->with('status', $result->message ?? 'Awaiting payment confirmation...');
        }

        $subscription = $activator->activate($order->refresh());

        return view('portal.autologin', [
            'brandName'    => Tenant::find($context->tenantId)?->brand_name ?? $this->defaultBrandName(),
            'subscription' => $subscription,
            'context'      => $context,
            'plan'         => $plan,
        ]);
    }

    /**
     * GET /status — show the current plan, fixed expiry time and minutes left
     * for this device. Reachable once the phone is logged in.
     */
    public function status(Request $request): View
    {
        $context = PortalContext::fromSession($request);

        $subscription = null;

        if ($context && $context->mac && $context->tenantId) {
            $device = Device::where('tenant_id', $context->tenantId)
                ->where('mac', $context->mac)
                ->first();

            if ($device) {
                $subscription = Subscription::with('order.plan')
                    ->where('device_id', $device->id)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->where('expires_at', '>', now())
                    ->orderByDesc('expires_at')
                    ->first();
            }
        }

        return view('portal.status', [
            'brandName'    => Tenant::query()->value('brand_name') ?? $this->defaultBrandName(),
            'subscription' => $subscription,
        ]);
    }

    /**
     * If the device placed a free order within the cooldown window, return a
     * human-readable wait time (e.g. "2 hours 14 minutes"); otherwise null.
     */
    private function freeCooldownRemaining(Device $device): ?string
    {
        $hours = (int) config('hotspot.free_cooldown_hours');

        if ($hours <= 0) {
            return null;
        }

        $windowStart = now()->subHours($hours);

        $last = Order::where('device_id', $device->id)
            ->where('payment_method', Order::METHOD_FREE)
            ->where('status', Order::STATUS_PAID)
            ->where('created_at', '>=', $windowStart)
            ->latest('created_at')
            ->first();

        if (! $last) {
            return null;
        }

        $readyAt = $last->created_at->copy()->addHours($hours);

        return now()->lessThan($readyAt)
            ? now()->diffForHumans($readyAt, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
            : null;
    }

    private function defaultBrandName(): string
    {
        return Tenant::query()->value('brand_name') ?? 'Netwells WiFi';
    }
}
