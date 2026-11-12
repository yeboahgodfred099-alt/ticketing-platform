<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_index_only_lists_published_upcoming_events(): void
    {
        // 1. Published upcoming event (must be listed)
        $published = Event::factory()->create([
            'status' => EventStatus::PUBLISHED,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(11),
        ]);
        TicketType::factory()->create(['event_id' => $published->id, 'status' => 'active']);

        // 2. Draft event (must NOT be listed)
        Event::factory()->draft()->create([
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(11),
        ]);

        // 3. Past published event (must NOT be listed)
        Event::factory()->create([
            'status' => EventStatus::PUBLISHED,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(4),
        ]);

        $response = $this->getJson('/api/v1/public/events');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_public_event_detail_by_slug(): void
    {
        $event = Event::factory()->create([
            'name' => 'Chale Wote Street Art Festival',
            'slug' => 'chale-wote-festival-2026',
            'status' => EventStatus::PUBLISHED,
        ]);

        $ticket = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Pass',
            'price_minor' => 4000,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/public/events/chale-wote-festival-2026');

        $response->assertStatus(200)
            ->assertJsonPath('event.slug', 'chale-wote-festival-2026')
            ->assertJsonPath('event.ticket_types.0.id', $ticket->id)
            ->assertJsonPath('event.lowest_price_minor', 4000);
    }

    public function test_draft_or_nonexistent_event_returns_404(): void
    {
        $draftEvent = Event::factory()->draft()->create([
            'slug' => 'secret-unreleased-event',
        ]);

        $response = $this->getJson('/api/v1/public/events/secret-unreleased-event');

        $response->assertStatus(404)
            ->assertJson([
                'code' => 'EVENT_NOT_FOUND',
            ]);
    }
}

