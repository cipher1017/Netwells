<?php

namespace App\Contracts;

use App\Models\Subscription;

/**
 * Abstracts "give this subscription internet access" from how it is enforced.
 *
 * The current implementation talks to MikroTik hotspot users over the RouterOS
 * API. A future RADIUS-backed implementation can be swapped in via the
 * container without touching controllers or the ActivateOrder service.
 */
interface AccessProvisioner
{
    /**
     * Create or update the access grant for a subscription (idempotent).
     */
    public function grant(Subscription $subscription): void;

    /**
     * Remove the access grant and kick any live session for a subscription.
     */
    public function revoke(Subscription $subscription): void;
}
