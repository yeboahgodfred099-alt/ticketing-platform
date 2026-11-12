<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case ACTIVE = 'active';
    case CHECKED_IN = 'checked_in';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function isValidForAdmission(): bool
    {
        return $this === self::ACTIVE;
    }
}

