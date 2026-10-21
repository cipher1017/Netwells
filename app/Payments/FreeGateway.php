<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;

/**
 * No-cost gateway used during the free trial milestone: it marks the order
 * paid immediately so ActivateOrder can grant access right away.
 */
class FreeGateway implements PaymentGateway
{
    public function initiate(Order $order): PaymentResult
    {
        $order->forceFill([
            'payment_method' => Order::METHOD_FREE,
            'status'         => Order::STATUS_PAID,
            'paid_at'        => now(),
        ])->save();

        return PaymentResult::paid('Free trial — enjoy!');
    }
}
