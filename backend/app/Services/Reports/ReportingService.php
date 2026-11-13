<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\CheckIn;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Carbon;

class ReportingService
{
    /**
     * Compute comprehensive financial and operational metrics.
     *
     * @param string|null $eventId Optional filter by event
     * @param Carbon|null $startDate Optional filter by start date
     * @param Carbon|null $endDate Optional filter by end date
     * @return array<string, mixed>
     */
    public function getOverviewStats(
        ?string $eventId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        // 1. Orders Base Query (Paid orders)
        $ordersQuery = Order::where('status', OrderStatus::PAID);
        if ($eventId) {
            $ordersQuery->where('event_id', $eventId);
        }
        if ($startDate) {
            $ordersQuery->where('paid_at', '>=', $startDate);
        }
        if ($endDate) {
            $ordersQuery->where('paid_at', '<=', $endDate);
        }

        $grossRevenueMinor = (int) $ordersQuery->sum('total_amount_minor');
        $totalOrdersCount = (int) $ordersQuery->count();

        // 2. Payments Query (Success payments for fees calculation)
        $paymentsQuery = Payment::where('status', PaymentStatus::SUCCESS)
            ->whereHas('order', function ($query) use ($eventId, $startDate, $endDate) {
                if ($eventId) {
                    $query->where('event_id', $eventId);
                }
                if ($startDate) {
                    $query->where('paid_at', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('paid_at', '<=', $endDate);
                }
            });

        $feesAbsorbedMinor = (int) $paymentsQuery->sum('fees_minor');
        $netRevenueMinor = max(0, $grossRevenueMinor - $feesAbsorbedMinor);

        // 3. Tickets & Attendance
        $ticketsQuery = Ticket::whereIn('status', [TicketStatus::ACTIVE, TicketStatus::CHECKED_IN]);
        if ($eventId) {
            $ticketsQuery->where('event_id', $eventId);
        }
        $totalTicketsSold = (int) $ticketsQuery->count();

        $checkInsQuery = CheckIn::query();
        if ($eventId) {
            $checkInsQuery->whereHas('ticket', fn ($q) => $q->where('event_id', $eventId));
        }
        $totalCheckedIn = (int) $checkInsQuery->count();
        $attendanceRate = $totalTicketsSold > 0 ? round(($totalCheckedIn / $totalTicketsSold) * 100, 1) : 0;

        // 4. Payment Provider Breakdown (MTN vs Telecel vs AT)
        $providerBreakdown = Payment::where('status', PaymentStatus::SUCCESS)
            ->whereHas('order', function ($query) use ($eventId) {
                if ($eventId) {
                    $query->where('event_id', $eventId);
                }
            })
            ->selectRaw('mobile_money_provider, count(*) as count, sum(amount_minor) as total_minor')
            ->groupBy('mobile_money_provider')
            ->get()
            ->map(function ($row) {
                $providerCode = $row->mobile_money_provider ?: 'other';
                $labels = [
                    'mtn' => 'MTN Mobile Money',
                    'vod' => 'Telecel Cash',
                    'atl' => 'AT Money',
                ];
                return [
                    'provider' => $providerCode,
                    'label' => $labels[$providerCode] ?? ucfirst($providerCode),
                    'transactions_count' => (int) $row->count,
                    'total_minor' => (int) $row->total_minor,
                    'total_major' => number_format(((int) $row->total_minor) / 100, 2, '.', ''),
                ];
            });

        // 5. Ticket Tier Breakdown
        $ticketTypesQuery = TicketType::query();
        if ($eventId) {
            $ticketTypesQuery->where('event_id', $eventId);
        }
        $tierBreakdown = $ticketTypesQuery->get()->map(function (TicketType $tt) {
            $revenueMinor = $tt->quantity_sold * $tt->price_minor;
            return [
                'id' => $tt->id,
                'name' => $tt->name,
                'price_minor' => $tt->price_minor,
                'price_major' => number_format($tt->price_minor / 100, 2, '.', ''),
                'quantity_total' => $tt->quantity_total,
                'quantity_sold' => $tt->quantity_sold,
                'quantity_reserved' => $tt->quantity_reserved,
                'available' => max(0, $tt->quantity_total - $tt->quantity_sold - $tt->quantity_reserved),
                'revenue_minor' => $revenueMinor,
                'revenue_major' => number_format($revenueMinor / 100, 2, '.', ''),
                'status' => $tt->status->value,
            ];
        });

        return [
            'currency' => 'GHS',
            'gross_revenue_minor' => $grossRevenueMinor,
            'gross_revenue_major' => number_format($grossRevenueMinor / 100, 2, '.', ''),
            'fees_absorbed_minor' => $feesAbsorbedMinor,
            'fees_absorbed_major' => number_format($feesAbsorbedMinor / 100, 2, '.', ''),
            'net_revenue_minor' => $netRevenueMinor,
            'net_revenue_major' => number_format($netRevenueMinor / 100, 2, '.', ''),
            'total_orders' => $totalOrdersCount,
            'total_tickets_sold' => $totalTicketsSold,
            'total_checked_in' => $totalCheckedIn,
            'attendance_rate_percentage' => $attendanceRate,
            'providers' => $providerBreakdown,
            'ticket_tiers' => $tierBreakdown,
        ];
    }
}

