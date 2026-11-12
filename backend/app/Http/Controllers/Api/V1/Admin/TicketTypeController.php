<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTicketTypeRequest;
use App\Http\Requests\Admin\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\Events\TicketTypeService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TicketTypeController extends Controller
{
    public function __construct(
        private readonly TicketTypeService $ticketTypeService
    ) {}

    public function index(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $ticketTypes = $event->ticketTypes()->orderBy('price_minor', 'asc')->get();

        return response()->json([
            'data' => TicketTypeResource::collection($ticketTypes),
        ]);
    }

    public function store(CreateTicketTypeRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $ticketType = $this->ticketTypeService->createTicketType($event, $request->validated());

        return response()->json([
            'message' => 'Ticket category created successfully.',
            'ticket_type' => new TicketTypeResource($ticketType),
        ], Response::HTTP_CREATED);
    }

    public function show(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('view', $ticketType);

        return response()->json([
            'ticket_type' => new TicketTypeResource($ticketType),
        ]);
    }

    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType): JsonResponse
    {
        $updated = $this->ticketTypeService->updateTicketType($ticketType, $request->validated());

        return response()->json([
            'message' => 'Ticket category updated successfully.',
            'ticket_type' => new TicketTypeResource($updated),
        ]);
    }

    public function archive(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('archive', $ticketType);

        $archived = $this->ticketTypeService->archiveTicketType($ticketType);

        return response()->json([
            'message' => 'Ticket category archived.',
            'ticket_type' => new TicketTypeResource($archived),
        ]);
    }
}

