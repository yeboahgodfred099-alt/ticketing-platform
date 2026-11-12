<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\CheckIn;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    protected $model = CheckIn::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'ticket_id' => Ticket::factory(),
            'admin_id' => Admin::factory(),
            'checked_in_at' => now(),
            'gate_identifier' => 'Gate-A1',
        ];
    }
}

