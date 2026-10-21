<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Router\RouterClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

/**
 * Configures a MikroTik router so unauthenticated phones are redirected to this
 * portal and can reach it before paying:
 *
 *   1. Backs up the hotspot profile's current html-directory (locally).
 *   2. Adds a walled-garden entry so the portal IP is reachable while unauth.
 *   3. Uploads our hotspot HTML pages (login.html etc.) into a dedicated
 *      directory on the router.
 *   4. Points the hotspot profile at that directory.
 *
 * Everything is idempotent. Use --dry-run to preview, --revert to undo.
 */
class HotspotConfigure extends Command
{
    protected $signature = 'hotspot:configure
        {identity? : Router identity (defaults to the first router)}
        {--dir=netwells : Directory name on the router to install pages into}
        {--dry-run : Show what would change without writing anything}
        {--revert : Restore the previous html-directory and remove our files}';

    protected $description = 'Install the captive-portal redirect + walled-garden on a router';

    private bool $dry = false;

    public function handle(RouterClientFactory $factory): int
    {
        $this->dry = (bool) $this->option('revert') ? false : (bool) $this->option('dry-run');
        $dir = trim((string) $this->option('dir'), '/');

        $router = $this->argument('identity')
            ? Router::where('identity', $this->argument('identity'))->first()
            : Router::orderBy('id')->first();

        if (! $router) {
            $this->error('No matching router found. Run the seeder first.');

            return self::FAILURE;
        }

        $portalBase = rtrim((string) config('app.url'), '/');
        $portalHost = parse_url($portalBase, PHP_URL_HOST) ?: $portalBase;

        $this->line("Router  : <info>{$router->identity}</info> ({$router->host})");
        $this->line("Portal  : <info>{$portalBase}</info>  (walled-garden host {$portalHost})");
        $this->line("Pages   : directory <info>{$dir}</info> on the router");
        $this->newLine();

        try {
            $client = $factory->make($router);

            [$profileId, $profileName, $currentDir] = $this->resolveProfile($client);
            $this->line("Hotspot profile: {$profileName}  (html-directory currently: '" . ($currentDir ?: '(empty / built-in)') . "')");

            if ($this->option('revert')) {
                return $this->revert($client, $router, $portalHost, $dir, $profileId);
            }

            // 1. Back up the current html-directory value.
            $this->backup($router, $profileName, $currentDir, $dir);

            // 2. Walled-garden.
            $this->ensureWalledGarden($client, $portalHost);

            // 3. Upload pages.
            $this->uploadPages($client, $dir, $portalBase, $router->identity);

            // 4. Repoint the profile.
            $this->setHtmlDirectory($client, $profileId, $dir);

            $this->newLine();
            $this->info($this->dry
                ? 'Dry run complete — no changes were made.'
                : "Done. Unauthenticated phones on {$router->identity} will now be sent to the portal.");

            if (! $this->dry) {
                $this->line('Test from a phone joined to the WiFi. To undo: php artisan hotspot:configure ' . $router->identity . ' --revert');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Find the hotspot server on this router, its profile .id/name and the
     * profile's current html-directory.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function resolveProfile(Client $client): array
    {
        $servers = $client->query(new Query('/ip/hotspot/print'))->read();

        if (empty($servers)) {
            throw new \RuntimeException('No hotspot server found on this router.');
        }

        $profileName = $servers[0]['profile'] ?? 'default';

        $profiles = $client->query(
            (new Query('/ip/hotspot/profile/print'))->where('name', $profileName)
        )->read();

        if (empty($profiles)) {
            throw new \RuntimeException("Hotspot profile '{$profileName}' not found.");
        }

        return [
            $profiles[0]['.id'],
            $profileName,
            $profiles[0]['html-directory'] ?? '',
        ];
    }

    private function backup(Router $router, string $profileName, string $currentDir, string $newDir): void
    {
        $path = storage_path("app/netwells-router/{$router->identity}.json");

        $this->line("• Backup current html-directory '" . ($currentDir ?: '(built-in)') . "' -> {$path}");

        if ($this->dry) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'identity'          => $router->identity,
            'profile'           => $profileName,
            'html_directory'    => $currentDir,
            'installed_dir'     => $newDir,
            'backed_up_at'      => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    private function ensureWalledGarden(Client $client, string $portalHost): void
    {
        $existing = $client->query(new Query('/ip/hotspot/walled-garden/ip/print'))->read();

        foreach ($existing as $row) {
            if (($row['dst-address'] ?? null) === $portalHost && ($row['action'] ?? '') === 'accept') {
                $this->line("• Walled-garden already allows {$portalHost} — ok");

                return;
            }
        }

        $this->line("• Walled-garden: allow {$portalHost} (accept)");

        if ($this->dry) {
            return;
        }

        $client->query(
            (new Query('/ip/hotspot/walled-garden/ip/add'))
                ->equal('dst-address', $portalHost)
                ->equal('action', 'accept')
                ->equal('comment', 'netwells-portal')
        )->read();
    }

    private function uploadPages(Client $client, string $dir, string $portalBase, string $identity): void
    {
        $files = File::files(resource_path('router/hotspot'));

        foreach ($files as $file) {
            $name = $file->getFilename();
            $target = "{$dir}/{$name}";

            $contents = str_replace(
                ['{{PORTAL_URL}}', '{{ROUTER_IDENTITY}}'],
                [$portalBase, $identity],
                File::get($file->getPathname()),
            );

            $this->line("• Upload {$target} (" . strlen($contents) . " bytes)");

            if ($this->dry) {
                continue;
            }

            $this->removeFileByName($client, $target);

            $client->query(
                (new Query('/file/add'))
                    ->equal('name', $target)
                    ->equal('contents', $contents)
            )->read();
        }
    }

    private function setHtmlDirectory(Client $client, string $profileId, string $dir): void
    {
        $this->line("• Set hotspot profile html-directory = {$dir}");

        if ($this->dry) {
            return;
        }

        $client->query(
            (new Query('/ip/hotspot/profile/set'))
                ->equal('.id', $profileId)
                ->equal('html-directory', $dir)
        )->read();
    }

    private function revert(Client $client, Router $router, string $portalHost, string $dir, string $profileId): int
    {
        $path = storage_path("app/netwells-router/{$router->identity}.json");

        if (! File::exists($path)) {
            $this->error("No backup found at {$path}; cannot revert automatically.");

            return self::FAILURE;
        }

        $backup = json_decode(File::get($path), true);
        $restoreDir = $backup['html_directory'] ?? '';
        $installedDir = $backup['installed_dir'] ?? $dir;

        $this->line("• Restore html-directory = '" . ($restoreDir ?: '(built-in)') . "'");
        $client->query(
            (new Query('/ip/hotspot/profile/set'))
                ->equal('.id', $profileId)
                ->equal('html-directory', $restoreDir)
        )->read();

        // Remove our uploaded pages.
        $files = File::files(resource_path('router/hotspot'));
        foreach ($files as $file) {
            $this->removeFileByName($client, "{$installedDir}/{$file->getFilename()}");
        }
        $this->removeFileByName($client, $installedDir); // the directory entry

        // Remove the walled-garden entry we added.
        $wg = $client->query(new Query('/ip/hotspot/walled-garden/ip/print'))->read();
        foreach ($wg as $row) {
            if (($row['dst-address'] ?? null) === $portalHost
                && ($row['action'] ?? '') === 'accept'
                && ($row['comment'] ?? '') === 'netwells-portal'
                && isset($row['.id'])) {
                $client->query((new Query('/ip/hotspot/walled-garden/ip/remove'))->equal('.id', $row['.id']))->read();
                $this->line("• Removed walled-garden entry for {$portalHost}");
            }
        }

        $this->info('Revert complete. Router restored to its previous hotspot pages.');

        return self::SUCCESS;
    }

    private function removeFileByName(Client $client, string $name): void
    {
        $q = new Query('/file/print');
        $q->add('=.proplist=.id,name');
        $q->where('name', $name);

        foreach ($client->query($q)->read() as $row) {
            if (isset($row['.id'])) {
                $client->query((new Query('/file/remove'))->equal('.id', $row['.id']))->read();
            }
        }
    }
}
