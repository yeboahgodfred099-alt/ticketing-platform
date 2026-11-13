<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * Stream a data-minimized CSV export of customer orders.
     */
    public function exportOrders(?string $eventId = null): StreamedResponse
    {
        $filename = 'orders-export-' . Carbon::now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($eventId) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Order Number',
                'Event Name',
                'Customer Name',
                'Customer Email',
                'Customer Phone (Masked)',
                'Tickets Count',
                'Total Amount (GHS)',
                'Status',
                'Paid At (UTC)',
                'Created At (UTC)',
            ]);

            $query = Order::query()->with(['event', 'items']);
            if ($eventId) {
                $query->where('event_id', $eventId);
            }

            $query->orderByDesc('created_at')->chunk(200, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    $ticketsCount = $order->items->sum('quantity');
                    $maskedPhone = $this->maskPhone($order->customer_phone);

                    fputcsv($handle, [
                        $order->order_number,
                        $order->event->name ?? 'N/A',
                        $order->customer_name,
                        $order->customer_email,
                        $maskedPhone,
                        $ticketsCount,
                        number_format($order->total_amount_minor / 100, 2, '.', ''),
                        $order->status->value,
                        $order->paid_at?->toIso8601String() ?? 'N/A',
                        $order->created_at->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Stream a data-minimized CSV export of event attendees and tickets.
     */
    public function exportAttendees(?string $eventId = null): StreamedResponse
    {
        $filename = 'attendees-export-' . Carbon::now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($eventId) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Ticket Number',
                'Event Name',
                'Category',
                'Attendee Name',
                'Attendee Email',
                'Status',
                'Gate Admitted',
                'Checked In At (UTC)',
                'Issued At (UTC)',
            ]);

            $query = Ticket::query()->with(['event', 'orderItem', 'checkIn']);
            if ($eventId) {
                $query->where('event_id', $eventId);
            }

            $query->orderBy('ticket_number')->chunk(200, function ($tickets) use ($handle) {
                foreach ($tickets as $ticket) {
                    fputcsv($handle, [
                        $ticket->ticket_number,
                        $ticket->event->name ?? 'N/A',
                        $ticket->orderItem?->ticket_name_snapshot ?? 'General Admission',
                        $ticket->holder_name,
                        $ticket->holder_email ?? 'N/A',
                        $ticket->status->value,
                        $ticket->checkIn?->gate_identifier ?? 'N/A',
                        $ticket->used_at?->toIso8601String() ?? 'N/A',
                        $ticket->issued_at->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Mask phone number for data minimization (e.g. +233241234567 -> +233****4567).
     */
    protected function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 6) {
            return '****';
        }

        $prefix = substr($phone, 0, 4);
        $suffix = substr($phone, -4);
        return "{$prefix}****{$suffix}";
    }
}

