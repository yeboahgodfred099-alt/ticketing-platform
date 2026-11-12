<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'ticket_id' => Ticket::factory(),
            'channel' => 'email',
            'recipient_masked' => 'c***@example.com',
            'template_key' => 'tickets_confirmation',
            'status' => NotificationStatus::SENT,
            'provider_message_id' => 'msg_' . Str::random(16),
            'attempt_count' => 1,
        ];
    }
}

