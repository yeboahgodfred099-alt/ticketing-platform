<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = 5000;

        return [
            'id' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'ticket_name_snapshot' => 'General Admission',
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'total_minor' => $quantity * $unitPrice,
            'currency' => 'GHS',
        ];
    }
}

