<?php

namespace App\Services;

use App\Contracts\AccessProvisioner;
use App\Models\Device;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a PAID order into live access.
 *
 * - If the device already has an ACTIVE subscription, the purchase EXTENDS it:
 *     expires_at = max(now, current expires_at) + plan duration.
 * - Otherwise a fresh subscription is created.
 * - The whole thing runs in a transaction and is IDEMPOTENT: re-activating the
 *   same order (e.g. a duplicate STK callback) never creates a second
 *   subscription nor extends twice, thanks to the orders.activated_at guard.
 *
 * The router grant happens inside the transaction: if it throws, the DB changes
 * roll back so we never record access we failed to provision.
 */
class ActivateOrder
{
    public function __construct(private readonly AccessProvisioner $provisioner)
    {
    }

    public function activate(Order $order): Subscription
    {
        if (! $order->isPaid()) {
            throw new RuntimeException("Order {$order->uuid} is not paid; cannot activate.");
        }

        return DB::transaction(function () use ($order) {
            // Lock the order row so concurrent activations serialise.
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Idempotency: already activated → return the subscription it produced.
            if ($order->activated_at !== null) {
                return $this->subscriptionForActivatedOrder($order);
            }

            $plan = $order->plan()->firstOrFail();

            $active = Subscription::where('device_id', $order->device_id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->lockForUpdate()
                ->orderByDesc('expires_at')
                ->first();

            if ($active) {
                $subscription = $this->extend($active, $order, $plan->duration_minutes);
            } else {
                $subscription = $this->create($order, $plan->router_profile, $plan->duration_minutes);
            }

            $this->provisioner->grant($subscription);

            $order->forceFill(['activated_at' => now()])->save();

            return $subscription;
        });
    }

    private function create(Order $order, string $routerProfile, int $durationMinutes): Subscription
    {
        $now = now();

        return Subscription::create([
            'tenant_id'        => $order->tenant_id,
            'order_id'         => $order->id,
            'device_id'        => $order->device_id,
            'router_id'        => $order->router_id,
            'hotspot_username' => $this->usernameFor($order->device_id),
            'hotspot_password' => Str::random(8),
            'starts_at'        => $now,
            'expires_at'       => $now->copy()->addMinutes($durationMinutes),
            'status'           => Subscription::STATUS_ACTIVE,
        ]);
    }

    private function extend(Subscription $subscription, Order $order, int $durationMinutes): Subscription
    {
        $now  = now();
        $base = $subscription->expires_at->greaterThan($now) ? $subscription->expires_at : $now;

        $subscription->forceFill([
            // Point the subscription at the most recent order that funded it.
            'order_id'   => $order->id,
            'expires_at' => $base->copy()->addMinutes($durationMinutes),
            'status'     => Subscription::STATUS_ACTIVE,
        ])->save();

        return $subscription;
    }

    /**
     * On an idempotent replay, return the subscription this order contributed to.
     * For a freshly-created subscription order_id matches; for an extend the
     * subscription was re-pointed at this order, so this still resolves.
     */
    private function subscriptionForActivatedOrder(Order $order): Subscription
    {
        return Subscription::where('order_id', $order->id)
            ->latest('id')
            ->firstOr(fn () => Subscription::where('device_id', $order->device_id)
                ->orderByDesc('expires_at')
                ->firstOrFail());
    }

    /**
     * Hotspot username is the device MAC (uppercase, colon-separated).
     */
    private function usernameFor(int $deviceId): string
    {
        return Device::whereKey($deviceId)->value('mac');
    }
}
