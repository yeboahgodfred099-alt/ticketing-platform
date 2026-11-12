<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\TicketTypeStatus;
use App\Models\Admin;
use App\Models\Event;
use App\Models\Role;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoEventSeeder extends Seeder
{
    public function run(): void
    {
        // Create a local demo Super Admin for development environment
        $admin = Admin::firstOrCreate(
            ['email' => 'admin@clevawave.com'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Demo Super Admin',
                'password' => Hash::make('Secret123!SecureAdminPassword'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole && ! $admin->hasRole('Super Admin')) {
            $admin->roles()->attach($superAdminRole->id, ['assigned_at' => now()]);
        }

        // Demo Event: Accra Tech & Music Summit 2026
        $event = Event::firstOrCreate(
            ['slug' => 'accra-music-summit-2026'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Accra Music & Culture Summit 2026',
                'description' => 'The premier gathering of music innovators, artists, and live performances in Accra.',
                'banner_path' => null,
                'venue_name' => 'Accra International Conference Centre',
                'venue_address' => 'Castle Road, Osu, Accra, Ghana',
                'starts_at' => now()->addDays(30)->setTime(18, 0),
                'ends_at' => now()->addDays(30)->setTime(23, 59),
                'sales_starts_at' => now()->subDays(5),
                'sales_ends_at' => now()->addDays(30)->setTime(17, 0),
                'timezone' => 'Africa/Accra',
                'status' => EventStatus::PUBLISHED,
                'created_by' => $admin->id,
            ]
        );

        // Ticket Types
        $ticketTypes = [
            [
                'name' => 'Early Bird Admission',
                'description' => 'Discounted general admission with access to all main stages.',
                'price_minor' => 5000, // GH₵ 50.00
                'currency' => 'GHS',
                'quantity_total' => 200,
                'quantity_sold' => 0,
                'quantity_reserved' => 0,
                'min_per_order' => 1,
                'max_per_order' => 5,
                'status' => TicketTypeStatus::ACTIVE,
            ],
            [
                'name' => 'Standard Admission',
                'description' => 'Full access admission to summit sessions and evening concert.',
                'price_minor' => 10000, // GH₵ 100.00
                'currency' => 'GHS',
                'quantity_total' => 500,
                'quantity_sold' => 0,
                'quantity_reserved' => 0,
                'min_per_order' => 1,
                'max_per_order' => 10,
                'status' => TicketTypeStatus::ACTIVE,
            ],
            [
                'name' => 'VIP Golden Circle',
                'description' => 'Premium lounge seating, welcome drinks, priority entrance, and artist meet & greet.',
                'price_minor' => 35000, // GH₵ 350.00
                'currency' => 'GHS',
                'quantity_total' => 50,
                'quantity_sold' => 0,
                'quantity_reserved' => 0,
                'min_per_order' => 1,
                'max_per_order' => 4,
                'status' => TicketTypeStatus::ACTIVE,
            ],
        ];

        foreach ($ticketTypes as $ticketData) {
            TicketType::firstOrCreate(
                ['event_id' => $event->id, 'name' => $ticketData['name']],
                array_merge(['id' => (string) Str::uuid()], $ticketData)
            );
        }
    }
}

