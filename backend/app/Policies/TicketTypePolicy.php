<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\TicketType;

class TicketTypePolicy
{
    public function before(Admin $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('ticket_types.view');
    }

    public function view(Admin $user, TicketType $ticketType): bool
    {
        return $user->hasPermission('ticket_types.view');
    }

    public function create(Admin $user): bool
    {
        return $user->hasPermission('ticket_types.create');
    }

    public function update(Admin $user, TicketType $ticketType): bool
    {
        return $user->hasPermission('ticket_types.update');
    }

    public function archive(Admin $user, TicketType $ticketType): bool
    {
        return $user->hasPermission('ticket_types.archive');
    }
}

