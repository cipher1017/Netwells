<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Router\RouterClientFactory;
use Illuminate\Console\Command;
use RouterOS\Query;
use Throwable;

class RouterPing extends Command
{
    protected $signature = 'router:ping {identity? : Router identity (defaults to the first router)}';

    protected $description = 'Connect to a MikroTik router and print its identity and hotspot user count';

    public function handle(RouterClientFactory $factory): int
    {
        $router = $this->resolveRouter($this->argument('identity'));

        if (! $router) {
            $this->error('No matching router found. Have you run the seeder?');

            return self::FAILURE;
        }

        $this->line("Connecting to <info>{$router->identity}</info> at {$router->host}:{$router->api_port} as {$router->api_user} ...");

        try {
            $client = $factory->make($router);

            $identity = $client->query(new Query('/system/identity/print'))->read();
            $users    = $client->query(new Query('/ip/hotspot/user/print'))->read();

            $name = $identity[0]['name'] ?? '(unknown)';

            $this->info("Connected.");
            $this->line("  Router identity : <info>{$name}</info>");
            $this->line('  Hotspot users   : <info>' . count($users) . '</info>');

            if ($name !== $router->identity) {
                $this->warn("  Note: router reports identity '{$name}' but DB row is '{$router->identity}'.");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Connection failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveRouter(?string $identity): ?Router
    {
        if ($identity) {
            return Router::where('identity', $identity)->first();
        }

        return Router::orderBy('id')->first();
    }
}
