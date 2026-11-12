<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketTypeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';

    public function isAvailable(): bool
    {
        return $this === self::ACTIVE;
    }
}

