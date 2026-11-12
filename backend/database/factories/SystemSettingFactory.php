<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'key' => fake()->unique()->slug(2),
            'value' => 'sample_value',
            'type' => 'string',
            'description' => fake()->sentence(),
            'is_public' => false,
        ];
    }
}

