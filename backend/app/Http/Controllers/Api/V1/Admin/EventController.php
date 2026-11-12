<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerUploadRequest;
use App\Http\Requests\Admin\CreateEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Admin;
use App\Models\Event;
use App\Services\Events\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::with(['ticketTypes', 'creator'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate((int) min(50, max(1, (int) $request->input('per_page', 15))));

        return response()->json([
            'data' => EventResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function store(CreateEventRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $event = $this->eventService->createEvent($request->validated(), $admin);

        return response()->json([
            'message' => 'Event created as draft.',
            'event' => new EventResource($event->load('ticketTypes')),
        ], Response::HTTP_CREATED);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json([
            'event' => new EventResource($event->load(['ticketTypes', 'creator'])),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $updated = $this->eventService->updateEvent($event, $request->validated());

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => new EventResource($updated->load('ticketTypes')),
        ]);
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        $this->authorize('publish', $event);

        $published = $this->eventService->publishEvent($event);

        return response()->json([
            'message' => 'Event published and visible for ticket purchases.',
            'event' => new EventResource($published->load('ticketTypes')),
        ]);
    }

    public function unpublish(Request $request, Event $event): JsonResponse
    {
        $this->authorize('publish', $event);

        $unpublished = $this->eventService->unpublishEvent($event);

        return response()->json([
            'message' => 'Event unpublished.',
            'event' => new EventResource($unpublished->load('ticketTypes')),
        ]);
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        $this->authorize('cancel', $event);

        $cancelled = $this->eventService->cancelEvent($event, $request->input('reason'));

        return response()->json([
            'message' => 'Event cancelled.',
            'event' => new EventResource($cancelled->load('ticketTypes')),
        ]);
    }

    public function archive(Request $request, Event $event): JsonResponse
    {
        $this->authorize('archive', $event);

        $archived = $this->eventService->archiveEvent($event);

        return response()->json([
            'message' => 'Event archived.',
            'event' => new EventResource($archived->load('ticketTypes')),
        ]);
    }

    public function uploadBanner(BannerUploadRequest $request, Event $event): JsonResponse
    {
        $path = $this->eventService->uploadBanner($event, $request->file('banner'));

        return response()->json([
            'message' => 'Banner uploaded successfully.',
            'banner_url' => url('storage/' . $path),
            'event' => new EventResource($event->fresh()->load('ticketTypes')),
        ]);
    }
}

