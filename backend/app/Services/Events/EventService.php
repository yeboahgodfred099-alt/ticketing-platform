<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Models\Admin;
use App\Models\Event;
use App\Services\Admin\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EventService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Create an event with auto-generated unique slug.
     *
     * @param array<string, mixed> $data
     */
    public function createEvent(array $data, Admin $creator): Event
    {
        $slug = ! empty($data['slug'])
            ? Str::slug($data['slug'])
            : $this->generateUniqueSlug($data['name']);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'banner_path' => null,
            'venue_name' => $data['venue_name'] ?? null,
            'venue_address' => $data['venue_address'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'sales_starts_at' => $data['sales_starts_at'] ?? null,
            'sales_ends_at' => $data['sales_ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? 'Africa/Accra',
            'status' => EventStatus::DRAFT,
            'created_by' => $creator->id,
        ]);

        $this->auditService->log('event.created', $event, ['name' => $event->name, 'slug' => $event->slug]);

        return $event;
    }

    /**
     * Update an event.
     *
     * @param array<string, mixed> $data
     */
    public function updateEvent(Event $event, array $data): Event
    {
        if (isset($data['name']) && empty($data['slug']) && $event->status === EventStatus::DRAFT) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $event->id);
        }

        $event->update($data);

        $this->auditService->log('event.updated', $event, ['updated_keys' => array_keys($data)]);

        return $event->fresh();
    }

    /**
     * Publish an event. Must have at least one active ticket type.
     */
    public function publishEvent(Event $event): Event
    {
        if ($event->ticketTypes()->where('status', 'active')->count() === 0) {
            throw new InvalidArgumentException('Cannot publish event without at least one active ticket category.');
        }

        $event->update(['status' => EventStatus::PUBLISHED]);

        $this->auditService->log('event.published', $event);

        return $event;
    }

    /**
     * Unpublish an event back to unpublished status.
     */
    public function unpublishEvent(Event $event): Event
    {
        $event->update(['status' => EventStatus::UNPUBLISHED]);

        $this->auditService->log('event.unpublished', $event);

        return $event;
    }

    /**
     * Cancel an event.
     */
    public function cancelEvent(Event $event, ?string $reason = null): Event
    {
        $event->update(['status' => EventStatus::CANCELLED]);

        $this->auditService->log('event.cancelled', $event, ['reason' => $reason]);

        return $event;
    }

    /**
     * Archive an event (soft/lifecycle preservation).
     */
    public function archiveEvent(Event $event): Event
    {
        $event->update(['status' => EventStatus::ARCHIVED]);

        $this->auditService->log('event.archived', $event);

        return $event;
    }

    /**
     * Securely upload and store event banner image.
     */
    public function uploadBanner(Event $event, UploadedFile $file): string
    {
        // Generate random non-guessable filename
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'banners/' . Str::random(40) . '.' . $extension;

        // Store to configured disk
        $disk = config('filesystems.default', 'local');
        $path = Storage::disk($disk)->putFileAs('public/banners', $file, basename($filename));

        // Delete old banner if present
        if ($event->banner_path) {
            Storage::disk($disk)->delete('public/' . $event->banner_path);
        }

        $storedPath = 'banners/' . basename($path);
        $event->update(['banner_path' => $storedPath]);

        $this->auditService->log('event.banner_uploaded', $event, ['path' => $storedPath]);

        return $storedPath;
    }

    /**
     * Generate a unique slug for an event.
     */
    private function generateUniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Event::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

