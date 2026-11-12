<?php

declare(strict_types=1);

namespace App\Enums;

enum EventStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case UNPUBLISHED = 'unpublished';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    /**
     * Whether public customers can view and purchase tickets for this event.
     */
    public function isPurchasable(): bool
    {
        return $this === self::PUBLISHED;
    }
}
