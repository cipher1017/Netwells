<?php

namespace Tests\Support;

use App\Contracts\AccessProvisioner;
use App\Models\Subscription;

/**
 * In-memory AccessProvisioner for tests: records grant/revoke calls instead of
 * talking to a real router.
 */
class FakeAccessProvisioner implements AccessProvisioner
{
    /** @var array<int, array{sub: int, username: string, expires: string}> */
    public array $granted = [];

    /** @var array<int, array{sub: int, username: string}> */
    public array $revoked = [];

    public function grant(Subscription $subscription): void
    {
        $this->granted[] = [
            'sub'      => $subscription->id,
            'username' => $subscription->hotspot_username,
            'expires'  => (string) $subscription->expires_at,
        ];
    }

    public function revoke(Subscription $subscription): void
    {
        $this->revoked[] = [
            'sub'      => $subscription->id,
            'username' => $subscription->hotspot_username,
        ];
    }

    public function grantCount(): int
    {
        return count($this->granted);
    }

    public function revokeCount(): int
    {
        return count($this->revoked);
    }
}
