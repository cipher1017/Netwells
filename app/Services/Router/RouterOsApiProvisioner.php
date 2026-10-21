<?php

namespace App\Services\Router;

use App\Contracts\AccessProvisioner;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

/**
 * Enforces access by managing MikroTik hotspot users over the RouterOS API.
 *
 * The hotspot username is the device MAC (uppercase, colon-separated). grant()
 * creates the user if missing or updates it if present, so re-running it for an
 * extended subscription simply pushes the new profile/expiry. revoke() kicks any
 * live session and deletes the user.
 */
class RouterOsApiProvisioner implements AccessProvisioner
{
    public function __construct(private readonly RouterClientFactory $factory)
    {
    }

    public function grant(Subscription $subscription): void
    {
        $subscription->loadMissing(['router', 'device', 'order.plan']);

        $router   = $subscription->router;
        $username = $subscription->hotspot_username;
        $profile  = $subscription->order->plan->router_profile;
        $comment  = sprintf(
            'order:%s exp:%s',
            $subscription->order->uuid,
            $subscription->expires_at->toIso8601String(),
        );

        try {
            $client = $this->factory->make($router);

            $existingId = $this->findUserId($client, $username);

            $query = $existingId === null
                ? new Query('/ip/hotspot/user/add')
                : (new Query('/ip/hotspot/user/set'))->equal('.id', $existingId);

            $query
                ->equal('name', $username)
                ->equal('password', $subscription->hotspot_password)
                ->equal('profile', $profile)
                ->equal('mac-address', $subscription->device->mac)
                ->equal('server', $router->hotspot_server)
                ->equal('comment', $comment);

            $client->query($query)->read();

            $this->log('grant', $username, true, null, [
                'action'  => $existingId === null ? 'add' : 'set',
                'profile' => $profile,
                'router'  => $router->identity,
            ]);
        } catch (Throwable $e) {
            $this->log('grant', $username, false, $e->getMessage(), [
                'router' => $router->identity ?? null,
            ]);

            throw $e;
        }
    }

    public function revoke(Subscription $subscription): void
    {
        $subscription->loadMissing('router');

        $router   = $subscription->router;
        $username = $subscription->hotspot_username;

        try {
            $client = $this->factory->make($router);

            // 1) Kick any live session for this user.
            $active = $client->query(
                (new Query('/ip/hotspot/active/print'))->where('user', $username)
            )->read();

            foreach ($active as $session) {
                if (isset($session['.id'])) {
                    $client->query(
                        (new Query('/ip/hotspot/active/remove'))->equal('.id', $session['.id'])
                    )->read();
                }
            }

            // 2) Remove the hotspot user definition.
            $userId = $this->findUserId($client, $username);

            if ($userId !== null) {
                $client->query(
                    (new Query('/ip/hotspot/user/remove'))->equal('.id', $userId)
                )->read();
            }

            $this->log('revoke', $username, true, null, [
                'sessions_removed' => count($active),
                'user_removed'     => $userId !== null,
                'router'           => $router->identity,
            ]);
        } catch (Throwable $e) {
            $this->log('revoke', $username, false, $e->getMessage(), [
                'router' => $router->identity ?? null,
            ]);

            throw $e;
        }
    }

    /**
     * Return the RouterOS internal .id of a hotspot user, or null if absent.
     */
    private function findUserId(Client $client, string $username): ?string
    {
        $rows = $client->query(
            (new Query('/ip/hotspot/user/print'))->where('name', $username)
        )->read();

        return $rows[0]['.id'] ?? null;
    }

    /**
     * Structured log for every router API call. Never logs passwords.
     */
    private function log(string $operation, string $username, bool $success, ?string $error, array $context = []): void
    {
        $payload = array_merge([
            'operation' => $operation,
            'username'  => $username,
            'success'   => $success,
            'error'     => $error,
        ], $context);

        $message = sprintf('router.%s %s %s', $operation, $username, $success ? 'OK' : 'FAILED');

        $success
            ? Log::channel('router')->info($message, $payload)
            : Log::channel('router')->error($message, $payload);
    }
}
