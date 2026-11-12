<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketTypeStatus;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Regular Admission', 'VIP Admission', 'Early Bird', 'Student Pass']),
            'description' => fake()->sentence(),
            'price_minor' => fake()->randomElement([2000, 5000, 10000, 25000]), // pesewas
            'currency' => 'GHS',
            'quantity_total' => fake()->numberBetween(50, 500),
            'quantity_sold' => 0,
            'quantity_reserved' => 0,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'sales_starts_at' => now()->subDay(),
            'sales_ends_at' => now()->addDays(20),
            'status' => TicketTypeStatus::ACTIVE,
        ];
    }
}

