<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'admin_id' => Admin::factory(),
            'action' => fake()->randomElement(['event.published', 'ticket_type.created', 'admin.login', 'order.cancelled']),
            'target_type' => 'App\\Models\\Event',
            'target_id' => (string) Str::uuid(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => ['reason' => 'Routine management update'],
            'created_at' => now(),
        ];
    }
}

