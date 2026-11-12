<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job to release expired inventory holds.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $expiredCount = 0;

        // Fetch IDs of active reservations that have passed their expiration timestamp
        $expiredReservationIds = InventoryReservation::where('status', ReservationStatus::ACTIVE)
            ->where('expires_at', '<=', $now)
            ->pluck('id');

        if ($expiredReservationIds->isEmpty()) {
            return 0;
        }

        foreach ($expiredReservationIds as $reservationId) {
            DB::transaction(function () use ($reservationId, $now, &$expiredCount) {
                /** @var InventoryReservation|null $reservation */
                $reservation = InventoryReservation::where('id', $reservationId)
                    ->lockForUpdate()
                    ->first();

                // If already converted or released in a concurrent turn, skip
                if (!$reservation || $reservation->status !== ReservationStatus::ACTIVE) {
                    return;
                }

                /** @var TicketType|null $ticketType */
                $ticketType = TicketType::where('id', $reservation->ticket_type_id)
                    ->lockForUpdate()
                    ->first();

                if ($ticketType) {
                    // Release reserved quantity safely without going below 0
                    $newReserved = max(0, $ticketType->quantity_reserved - $reservation->quantity);
                    $ticketType->update(['quantity_reserved' => $newReserved]);
                }

                $reservation->update([
                    'status' => ReservationStatus::EXPIRED,
                    'released_at' => $now,
                ]);

                /** @var Order|null $order */
                $order = Order::where('id', $reservation->order_id)
                    ->lockForUpdate()
                    ->first();

                if ($order && $order->status === OrderStatus::PENDING_PAYMENT) {
                    // Check if all reservations for this order are non-active
                    $hasActiveReservations = InventoryReservation::where('order_id', $order->id)
                        ->where('status', ReservationStatus::ACTIVE)
                        ->exists();

                    if (!$hasActiveReservations) {
                        $order->update(['status' => OrderStatus::EXPIRED]);
                    }
                }

                $expiredCount++;
            });
        }

        if ($expiredCount > 0) {
            Log::info("Released {$expiredCount} expired inventory reservations.");
        }

        return $expiredCount;
    }
}
