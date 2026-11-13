<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Reports\CsvExportService;
use App\Services\Reports\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService,
        protected CsvExportService $csvExportService
    ) {}

    /**
     * Financial and operational metrics overview.
     */
    public function overview(Request $request): JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        if (!$user->hasPermission('reports.view')) {
            return response()->json([
                'message' => 'Unauthorized: Permission reports.view required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $eventId = $request->query('event_id');
        $fromStr = $request->query('from');
        $toStr = $request->query('to');

        $startDate = $fromStr ? Carbon::parse($fromStr) : null;
        $endDate = $toStr ? Carbon::parse($toStr) : null;

        $stats = $this->reportingService->getOverviewStats(
            $eventId ? (string) $eventId : null,
            $startDate,
            $endDate
        );

        return response()->json([
            'data' => $stats,
        ], Response::HTTP_OK);
    }

    /**
     * Export orders to data-minimized CSV stream.
     */
    public function exportOrders(Request $request): StreamedResponse|JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        if (!$user->hasPermission('orders.export')) {
            return response()->json([
                'message' => 'Unauthorized: Permission orders.export required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $eventId = $request->query('event_id');
        return $this->csvExportService->exportOrders($eventId ? (string) $eventId : null);
    }

    /**
     * Export attendees to data-minimized CSV stream.
     */
    public function exportAttendees(Request $request): StreamedResponse|JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        if (!$user->hasPermission('attendees.export')) {
            return response()->json([
                'message' => 'Unauthorized: Permission attendees.export required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $eventId = $request->query('event_id');
        return $this->csvExportService->exportAttendees($eventId ? (string) $eventId : null);
    }
}

