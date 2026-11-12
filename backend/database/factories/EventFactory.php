<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Admin;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $name = fake()->catchPhrase() . ' Festival ' . fake()->year();
        $startsAt = now()->addDays(fake()->numberBetween(10, 60));
        $endsAt = (clone $startsAt)->addHours(fake()->numberBetween(4, 12));

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'description' => fake()->paragraphs(2, true),
            'banner_path' => null,
            'venue_name' => fake()->company() . ' Arena',
            'venue_address' => fake()->address(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'sales_starts_at' => now()->subDay(),
            'sales_ends_at' => $startsAt,
            'timezone' => 'Africa/Accra',
            'status' => EventStatus::PUBLISHED,
            'created_by' => Admin::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => EventStatus::DRAFT,
        ]);
    }
}

