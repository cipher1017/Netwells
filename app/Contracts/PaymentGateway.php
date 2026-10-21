<?php

namespace App\Contracts;

use App\Models\Order;
use App\Payments\PaymentResult;

/**
 * Abstracts how an order gets paid.
 *
 * Current implementation: FreeGateway (marks the order paid immediately).
 * Next milestone: DarajaSandboxGateway (returns "pending" and completes via an
 * M-Pesa STK callback). Selected by config('hotspot.payment_mode').
 */
interface PaymentGateway
{
    public function initiate(Order $order): PaymentResult;
}
