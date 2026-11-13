<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidCheckoutException;
use App\Jobs\SendTicketEmail;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketIssuanceService
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {}

    /**
     * Issue digital tickets for a successfully paid order.
     *
     * @param Order $order
     * @return Collection<int, Ticket>
     *
     * @throws InvalidCheckoutException
     */
    public function issueTickets(Order $order): Collection
    {
        $order->loadMissing(['items', 'event']);

        if ($order->status !== OrderStatus::PAID) {
            throw new InvalidCheckoutException("Cannot issue tickets for unpaid order {$order->order_number} (status: {$order->status->value}).");
        }

        // Idempotency: check if tickets have already been generated for this order
        $existingTickets = Ticket::whereHas('orderItem', function ($query) use ($order) {
            $query->where('order_id', $order->id);
        })->get();

        if ($existingTickets->isNotEmpty()) {
            Log::info("Tickets already issued for order {$order->order_number}. Returning existing {$existingTickets->count()} tickets.");
            return $existingTickets;
        }

        return DB::transaction(function () use ($order) {
            $issuedTickets = new Collection();
            $ticketSequence = 1;
            $now = Carbon::now();

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $ticketId = (string) Str::uuid();
                    $ticketSeqFormatted = str_pad((string) $ticketSequence, 3, '0', STR_PAD_LEFT);
                    $ticketNumber = "TCK-{$order->order_number}-{$ticketSeqFormatted}";

                    // Generate high-entropy 256-bit token & SHA-256 hash
                    $tokenData = $this->qrCodeService->generateToken($ticketId, $ticketNumber);

                    /** @var Ticket $ticket */
                    $ticket = Ticket::create([
                        'id' => $ticketId,
                        'order_item_id' => $item->id,
                        'event_id' => $order->event_id,
                        'ticket_number' => $ticketNumber,
                        'qr_token_hash' => $tokenData['hash'],
                        'holder_name' => $order->customer_name,
                        'holder_email' => $order->customer_email,
                        'status' => TicketStatus::ACTIVE,
                        'issued_at' => $now,
                    ]);

                    $issuedTickets->push($ticket);
                    $ticketSequence++;
                }
            }

            Log::info("Issued {$issuedTickets->count()} tickets for order {$order->order_number}.");

            // Dispatch customer email delivery job after successful transaction commit
            SendTicketEmail::dispatch($order->id);

            return $issuedTickets;
        });
    }
}
