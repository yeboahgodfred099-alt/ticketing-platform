<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Event;

class EventPolicy
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
        return $user->hasPermission('events.view');
    }

    public function view(Admin $user, Event $event): bool
    {
        return $user->hasPermission('events.view');
    }

    public function create(Admin $user): bool
    {
        return $user->hasPermission('events.create');
    }

    public function update(Admin $user, Event $event): bool
    {
        return $user->hasPermission('events.update');
    }

    public function publish(Admin $user, Event $event): bool
    {
        return $user->hasPermission('events.publish');
    }

    public function cancel(Admin $user, Event $event): bool
    {
        return $user->hasPermission('events.cancel');
    }

    public function archive(Admin $user, Event $event): bool
    {
        return $user->hasPermission('events.archive');
    }
}

