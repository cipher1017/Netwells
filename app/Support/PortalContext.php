<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Router;
use Illuminate\Http\Request;

/**
 * The captive-portal handoff context for one phone.
 *
 * The router redirects unauthenticated phones to our landing URL with
 * ?mac=&ip=&router=&login=&dst=. We normalise those, resolve which router
 * (and therefore tenant) they belong to, and stash the result in the session
 * so later steps (purchase, auto-login) can use it without the phone having to
 * carry the parameters around.
 */
class PortalContext
{
    private const SESSION_KEY = 'portal';

    public function __construct(
        public readonly ?string $mac,
        public readonly ?string $ip,
        public readonly ?string $routerIdentity,
        public readonly ?int $routerId,
        public readonly ?int $tenantId,
        public readonly ?string $login,
        public readonly ?string $dst,
    ) {
    }

    /**
     * Build a context from the incoming request's query parameters,
     * resolving the router/tenant from the router identity when possible.
     */
    public static function fromRequest(Request $request): self
    {
        $mac = Device::normaliseMac($request->query('mac'));
        $identity = $request->query('router');

        $router = $identity
            ? Router::where('identity', $identity)->first()
            : null;

        return new self(
            mac: $mac,
            ip: $request->query('ip'),
            routerIdentity: $identity,
            routerId: $router?->id,
            tenantId: $router?->tenant_id,
            login: $request->query('login'),
            dst: $request->query('dst'),
        );
    }

    public static function fromSession(Request $request): ?self
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (! is_array($data)) {
            return null;
        }

        return new self(
            mac: $data['mac'] ?? null,
            ip: $data['ip'] ?? null,
            routerIdentity: $data['router_identity'] ?? null,
            routerId: $data['router_id'] ?? null,
            tenantId: $data['tenant_id'] ?? null,
            login: $data['login'] ?? null,
            dst: $data['dst'] ?? null,
        );
    }

    public function store(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'mac'             => $this->mac,
            'ip'              => $this->ip,
            'router_identity' => $this->routerIdentity,
            'router_id'       => $this->routerId,
            'tenant_id'       => $this->tenantId,
            'login'           => $this->login,
            'dst'             => $this->dst,
        ]);
    }

    /**
     * True when we have enough to show plans: a valid MAC and a known router.
     */
    public function isUsable(): bool
    {
        return $this->mac !== null && $this->routerId !== null;
    }

    public function router(): ?Router
    {
        return $this->routerId ? Router::find($this->routerId) : null;
    }
}
