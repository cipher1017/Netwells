<?php

namespace App\Services\Router;

use App\Models\Router;
use RouterOS\Client;

/**
 * Builds a configured RouterOS API Client from a Router model.
 *
 * Kept separate from the provisioner so the connection concern (credentials,
 * timeouts, SSL) lives in one place and can be mocked in tests.
 */
class RouterClientFactory
{
    public function make(Router $router): Client
    {
        return new Client([
            'host'     => $router->host,
            'user'     => $router->api_user,
            'pass'     => $router->api_password, // decrypted by the model cast
            'port'     => $router->api_port,
            'timeout'  => 5,
            'attempts' => 1,
        ]);
    }
}
