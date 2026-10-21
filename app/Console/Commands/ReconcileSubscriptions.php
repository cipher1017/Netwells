<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\Subscription;
use App\Services\Router\RouterClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

/**
 * Removes hotspot users that WE created (comment starts with "order:") but which
 * no longer map to an active subscription in the database — e.g. left behind by
 * a crash between grant and DB commit, or by manual testing. Run manually.
 *
 * Only our own users are ever touched; anything without an "order:" comment
 * (admin accounts, pre-existing users) is left alone.
 */
class ReconcileSubscriptions extends Command
{
    protected $signature = 'subscriptions:reconcile
        {identity? : Limit to one router identity (defaults to all routers)}
        {--dry-run : List orphans without removing them}';

    protected $description = 'Remove hotspot users we created that have no active subscription';

    public function handle(RouterClientFactory $factory): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $routers = $this->argument('identity')
            ? Router::where('identity', $this->argument('identity'))->get()
            : Router::all();

        if ($routers->isEmpty()) {
            $this->error('No matching router found.');

            return self::FAILURE;
        }

        $totalOrphans = 0;
        $totalRemoved = 0;

        foreach ($routers as $router) {
            $this->line("Router <info>{$router->identity}</info> ({$router->host}):");

            try {
                $client = $factory->make($router);
            } catch (Throwable $e) {
                $this->error("  cannot connect: {$e->getMessage()}");

                continue;
            }

            $activeUsernames = Subscription::where('router_id', $router->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where('expires_at', '>', now())
                ->pluck('hotspot_username')
                ->map(fn ($u) => strtoupper($u))
                ->flip();

            $users = $client->query(new Query('/ip/hotspot/user/print'))->read();

            foreach ($users as $user) {
                $name    = $user['name'] ?? null;
                $comment = $user['comment'] ?? '';

                // Only ever touch users we created.
                if ($name === null || ! str_starts_with($comment, 'order:')) {
                    continue;
                }

                if ($activeUsernames->has(strtoupper($name))) {
                    continue; // backed by a live subscription — keep.
                }

                $totalOrphans++;
                $this->warn("  orphan: {$name}  [{$comment}]");

                if ($dryRun) {
                    continue;
                }

                try {
                    $this->removeUser($client, $user);
                    $totalRemoved++;
                    Log::channel('router')->info('reconcile.remove OK', [
                        'router'   => $router->identity,
                        'username' => $name,
                    ]);
                } catch (Throwable $e) {
                    $this->error("    remove failed: {$e->getMessage()}");
                    Log::channel('router')->error('reconcile.remove FAILED', [
                        'router'   => $router->identity,
                        'username' => $name,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info($dryRun
            ? "Found {$totalOrphans} orphan(s). (dry run — nothing removed)"
            : "Found {$totalOrphans} orphan(s), removed {$totalRemoved}.");

        return self::SUCCESS;
    }

    private function removeUser(Client $client, array $user): void
    {
        $name = $user['name'];

        // Kick any live session first.
        $active = $client->query(
            (new Query('/ip/hotspot/active/print'))->where('user', $name)
        )->read();

        foreach ($active as $session) {
            if (isset($session['.id'])) {
                $client->query(
                    (new Query('/ip/hotspot/active/remove'))->equal('.id', $session['.id'])
                )->read();
            }
        }

        if (isset($user['.id'])) {
            $client->query(
                (new Query('/ip/hotspot/user/remove'))->equal('.id', $user['.id'])
            )->read();
        }
    }
}
