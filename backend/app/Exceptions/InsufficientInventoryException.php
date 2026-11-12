<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InsufficientInventoryException extends Exception
{
    public function __construct(
        string $ticketTypeName,
        int $requestedQuantity,
        int $availableQuantity
    ) {
        parent::__construct(
            "Insufficient inventory for '{$ticketTypeName}'. Requested {$requestedQuantity}, but only {$availableQuantity} remaining."
        );
    }
}
