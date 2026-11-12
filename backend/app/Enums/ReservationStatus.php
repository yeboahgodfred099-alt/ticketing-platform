<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case ACTIVE = 'active';
    case CONVERTED = 'converted';
    case RELEASED = 'released';
    case EXPIRED = 'expired';

    public function holdsInventory(): bool
    {
        return $this === self::ACTIVE;
    }
}

