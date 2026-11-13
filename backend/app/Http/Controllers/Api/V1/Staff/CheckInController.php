<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CheckInRequest;
use App\Models\Admin;
use App\Models\Event;
use App\Services\CheckIn\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInController extends Controller
{
    public function __construct(
        protected CheckInService $checkInService
    ) {}

    /**
     * Scan ticket QR token or manual ticket number to perform gate admission.
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        /** @var Admin $staff */
        $staff = $request->user();

        $result = $this->checkInService->validateAndCheckIn(
            $request->input('token'),
            $request->input('event_id'),
            $staff,
            $request->input('gate_identifier')
        );

        $ticket = $result['ticket'] ?? null;

        return response()->json([
            'outcome' => $result['outcome'],
            'message' => $result['message'],
            'data' => $ticket ? [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'holder_name' => $ticket->holder_name,
                'category' => $ticket->orderItem?->ticket_name_snapshot ?? 'Admission',
                'status' => $ticket->status->value,
                'checked_in_at' => $result['checked_in_at'] ?? null,
                'prior_check_in' => $result['prior_check_in'] ?? null,
            ] : null,
        ], Response::HTTP_OK);
    }

    /**
     * Get real-time check-in stats and recent scans for a specific event.
     */
    public function stats(Request $request, Event $event): JsonResponse
    {
        /** @var Admin|null $staff */
        $staff = $request->user();

        if (!$staff || !$staff->hasPermission('check_in.scan')) {
            return response()->json([
                'message' => 'Unauthorized: Gate scan permission required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $stats = $this->checkInService->getEventGateStats($event->id);

        return response()->json([
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'starts_at' => $event->starts_at->toIso8601String(),
                ],
                'stats' => $stats,
            ],
        ], Response::HTTP_OK);
    }
}

