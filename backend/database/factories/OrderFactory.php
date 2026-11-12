<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(5000, 50000);

        return [
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'event_id' => Event::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '055' . fake()->numerify('#######'),
            'subtotal_minor' => $amount,
            'discount_minor' => 0,
            'total_amount_minor' => $amount,
            'currency' => 'GHS',
            'status' => OrderStatus::PENDING_PAYMENT,
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
            'access_token_hash' => hash('sha256', Str::random(40)),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}

