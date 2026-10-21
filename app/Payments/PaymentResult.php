<?php

namespace App\Payments;

/**
 * The outcome of a PaymentGateway::initiate() call.
 *
 *   paid    — money (or a free grant) is secured; activate the order now.
 *   pending — awaiting an out-of-band confirmation (e.g. M-Pesa STK callback).
 *   failed  — the attempt was rejected; nothing was granted.
 */
class PaymentResult
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
    ) {
    }

    public static function paid(?string $message = null): self
    {
        return new self(self::PAID, $message);
    }

    public static function pending(?string $message = null): self
    {
        return new self(self::PENDING, $message);
    }

    public static function failed(?string $message = null): self
    {
        return new self(self::FAILED, $message);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }
}
