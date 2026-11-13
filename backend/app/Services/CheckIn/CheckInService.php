<?php

declare(strict_types=1);

namespace App\Services\CheckIn;

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\CheckIn;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckInService
{
    /**
     * Atomically validates and checks in a ticket at the event gate.
     *
     * @param string $tokenOrTicketNumber Scanned QR token or manual ticket number
     * @param string $eventId Target event UUID
     * @param Admin $staff Staff member performing check-in
     * @param string|null $gateIdentifier Optional gate or turnstile identifier (e.g. "Gate 1")
     * @return array{
     *     outcome: 'valid'|'already_used'|'wrong_event'|'invalid'|'cancelled',
     *     message: string,
     *     ticket: Ticket|null,
     *     checked_in_at?: string,
     *     prior_check_in?: array<string, mixed>|null
     * }
     */
    public function validateAndCheckIn(
        string $tokenOrTicketNumber,
        string $eventId,
        Admin $staff,
        ?string $gateIdentifier = null
    ): array {
        $input = trim($tokenOrTicketNumber);

        return DB::transaction(function () use ($input, $eventId, $staff, $gateIdentifier) {
            // Determine if input is a 64-char raw QR token hex or a ticket number
            $isRawQrToken = (bool) preg_match('/^[a-f0-9]{64}$/i', $input);

            $query = Ticket::query()->lockForUpdate()->with(['orderItem', 'event']);

            if ($isRawQrToken) {
                $hash = hash('sha256', $input);
                $ticket = $query->where('qr_token_hash', $hash)->first();
            } else {
                $ticket = $query->where('ticket_number', $input)->first();
            }

            // Outcome 1: Ticket not found
            if (!$ticket) {
                return [
                    'outcome' => 'invalid',
                    'message' => 'Invalid ticket: No matching record found.',
                    'ticket' => null,
                ];
            }

            // Outcome 2: Ticket belongs to a different event
            if ($ticket->event_id !== $eventId) {
                return [
                    'outcome' => 'wrong_event',
                    'message' => "Ticket is for another event ('{$ticket->event->name}').",
                    'ticket' => $ticket,
                ];
            }

            // Outcome 3: Ticket is cancelled or refunded
            if (in_array($ticket->status, [TicketStatus::CANCELLED, TicketStatus::REFUNDED], true)) {
                return [
                    'outcome' => 'cancelled',
                    'message' => "Ticket is {$ticket->status->value} and cannot be admitted.",
                    'ticket' => $ticket,
                ];
            }

            // Outcome 4: Ticket already checked in (Duplicate scan detection)
            if ($ticket->status === TicketStatus::CHECKED_IN || CheckIn::where('ticket_id', $ticket->id)->exists()) {
                /** @var CheckIn|null $priorCheckIn */
                $priorCheckIn = CheckIn::where('ticket_id', $ticket->id)->with('staff')->first();
                $checkedInAtStr = $priorCheckIn?->checked_in_at?->toIso8601String() ?? $ticket->used_at?->toIso8601String() ?? 'earlier';
                $gate = $priorCheckIn?->gate_identifier ? " at {$priorCheckIn->gate_identifier}" : "";
                $staffName = $priorCheckIn?->staff?->name ? " by {$priorCheckIn->staff->name}" : "";

                Log::warning("Duplicate ticket scan rejected: #{$ticket->ticket_number} was checked in{$gate}{$staffName} at {$checkedInAtStr}.");

                return [
                    'outcome' => 'already_used',
                    'message' => "ALREADY USED: Ticket was checked in at {$checkedInAtStr}{$gate}.",
                    'ticket' => $ticket,
                    'prior_check_in' => $priorCheckIn ? [
                        'checked_in_at' => $checkedInAtStr,
                        'gate' => $priorCheckIn->gate_identifier,
                        'staff_name' => $priorCheckIn->staff?->name,
                    ] : null,
                ];
            }

            // Outcome 5: Valid admission! Atomically record CheckIn and update Ticket
            $now = Carbon::now();

            CheckIn::create([
                'ticket_id' => $ticket->id,
                'admin_id' => $staff->id,
                'checked_in_at' => $now,
                'gate_identifier' => $gateIdentifier,
            ]);

            $ticket->update([
                'status' => TicketStatus::CHECKED_IN,
                'used_at' => $now,
            ]);

            Log::info("Admission granted: Ticket #{$ticket->ticket_number} ({$ticket->holder_name}) checked in at gate {$gateIdentifier} by staff {$staff->name}.");

            return [
                'outcome' => 'valid',
                'message' => 'Admission Granted!',
                'ticket' => $ticket,
                'checked_in_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * Get real-time gate statistics for an event.
     *
     * @param string $eventId
     * @return array<string, mixed>
     */
    public function getEventGateStats(string $eventId): array
    {
        $totalSold = Ticket::where('event_id', $eventId)
            ->whereIn('status', [TicketStatus::ACTIVE, TicketStatus::CHECKED_IN])
            ->count();

        $totalCheckedIn = Ticket::where('event_id', $eventId)
            ->where('status', TicketStatus::CHECKED_IN)
            ->count();

        $remaining = max(0, $totalSold - $totalCheckedIn);

        $recentCheckIns = CheckIn::whereHas('ticket', function ($query) use ($eventId) {
            $query->where('event_id', $eventId);
        })
            ->with(['ticket.orderItem', 'staff'])
            ->orderByDesc('checked_in_at')
            ->limit(10)
            ->get()
            ->map(fn (CheckIn $ci) => [
                'ticket_number' => $ci->ticket->ticket_number,
                'holder_name' => $ci->ticket->holder_name,
                'category' => $ci->ticket->orderItem?->ticket_name_snapshot ?? 'General Admission',
                'gate' => $ci->gate_identifier,
                'checked_in_at' => $ci->checked_in_at->toIso8601String(),
                'staff_name' => $ci->staff?->name,
            ]);

        return [
            'total_sold' => $totalSold,
            'total_checked_in' => $totalCheckedIn,
            'remaining_to_arrive' => $remaining,
            'check_in_percentage' => $totalSold > 0 ? round(($totalCheckedIn / $totalSold) * 100, 1) : 0,
            'recent_check_ins' => $recentCheckIns,
        ];
    }
}
