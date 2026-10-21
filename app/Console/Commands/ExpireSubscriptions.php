<?php

namespace App\Console\Commands;

use App\Contracts\AccessProvisioner;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enforces FIXED-time expiry: any active subscription whose expires_at has
 * passed is revoked on the router and marked expired. Scheduled every minute.
 *
 * If a revoke fails (e.g. router unreachable) the subscription is left active
 * so the next run retries — we never mark someone expired in the DB while they
 * still have working access on the router.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Revoke and expire subscriptions whose fixed end time has passed';

    public function handle(AccessProvisioner $provisioner): int
    {
        $due = Subscription::with(['router', 'device'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No subscriptions due for expiry.');

            return self::SUCCESS;
        }

        $expired = 0;
        $failed = 0;

        foreach ($due as $subscription) {
            try {
                $provisioner->revoke($subscription);
                $subscription->update(['status' => Subscription::STATUS_EXPIRED]);
                $expired++;
                $this->line("  expired {$subscription->hotspot_username}");
            } catch (Throwable $e) {
                $failed++;
                Log::channel('router')->error('expire.revoke FAILED', [
                    'subscription' => $subscription->id,
                    'username'     => $subscription->hotspot_username,
                    'error'        => $e->getMessage(),
                ]);
                $this->warn("  failed {$subscription->hotspot_username}: {$e->getMessage()} (will retry)");
            }
        }

        $this->info("Expired {$expired}, failed {$failed}, of {$due->count()} due.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
