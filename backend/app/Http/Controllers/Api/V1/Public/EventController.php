<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicEventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    /**
     * Public listing of published, upcoming events.
     */
    public function index(Request $request): JsonResponse
    {
        $events = Event::with('ticketTypes')
            ->where('status', EventStatus::PUBLISHED->value)
            ->where('ends_at', '>', now())
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('venue_name', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('starts_at', 'asc')
            ->paginate((int) min(30, max(1, (int) $request->input('per_page', 12))));

        return response()->json([
            'data' => PublicEventResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Public details of a published event identified by its unique slug.
     */
    public function show(string $slug): JsonResponse
    {
        $event = Event::with(['ticketTypes' => function ($q) {
            $q->where('status', 'active')->orderBy('price_minor', 'asc');
        }])
            ->where('slug', $slug)
            ->where('status', EventStatus::PUBLISHED->value)
            ->first();

        if (! $event) {
            return response()->json([
                'message' => 'Event not found or no longer available for ticket sales.',
                'code' => 'EVENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'event' => new PublicEventResource($event),
        ]);
    }
}

