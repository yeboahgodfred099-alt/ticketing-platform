<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case REQUIRES_ACTION = 'requires_action';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case ABANDONED = 'abandoned';
    case EXPIRED = 'expired';
    case REVERSED = 'reversed';
    case REFUNDED = 'refunded';

    public function isSuccessful(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::INITIATED, self::PENDING, self::REQUIRES_ACTION], true);
    }
}

