<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\TicketTypeStatus;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidCheckoutException;
use App\Models\Event;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\Support\PhoneNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    /**
     * Atomically reserves inventory and creates an anonymous guest Order.
     *
     * @param array{
     *     event_id?: string,
     *     event_slug?: string,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone: string,
     *     items: array<int, array{ticket_type_id: string, quantity: int}>
     * } $data
     * @return array{
     *     order: Order,
     *     raw_access_token: string,
     *     expires_at: Carbon
     * }
     *
     * @throws InvalidCheckoutException
     * @throws InsufficientInventoryException
     */
    public function reserveOrder(array $data): array
    {
        // 1. Resolve and validate Event
        $event = $this->resolveEvent($data);

        if ($event->status !== EventStatus::PUBLISHED) {
            throw new InvalidCheckoutException("Ticket sales are only permitted for published events.");
        }

        $now = Carbon::now();
        if ($event->sales_starts_at && $now->isBefore($event->sales_starts_at)) {
            throw new InvalidCheckoutException("Ticket sales for this event have not opened yet.");
        }
        if ($event->sales_ends_at && $now->isAfter($event->sales_ends_at)) {
            throw new InvalidCheckoutException("Ticket sales for this event have closed.");
        }

        // 2. Normalize and validate Customer Data
        $customerName = trim($data['customer_name']);
        $customerEmail = strtolower(trim($data['customer_email']));
        $customerPhone = PhoneNormalizer::normalize($data['customer_phone']);

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvalidCheckoutException("At least one ticket must be selected.");
        }

        // Aggregate quantities per ticket type
        $requestedQuantities = [];
        foreach ($items as $item) {
            $ttId = $item['ticket_type_id'];
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $requestedQuantities[$ttId] = ($requestedQuantities[$ttId] ?? 0) + $qty;
        }

        if (empty($requestedQuantities)) {
            throw new InvalidCheckoutException("Order must contain at least one ticket with quantity > 0.");
        }

        // DEADLOCK PREVENTION: Sort ticket type IDs alphabetically before acquiring locks
        $sortedTicketTypeIds = array_keys($requestedQuantities);
        sort($sortedTicketTypeIds, SORT_STRING);

        $ttlMinutes = (int) config('ticketing.reservation_ttl_minutes', 15);
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);
        $rawAccessToken = bin2hex(random_bytes(32));
        $accessTokenHash = hash('sha256', $rawAccessToken);

        return DB::transaction(function () use (
            $event,
            $customerName,
            $customerEmail,
            $customerPhone,
            $sortedTicketTypeIds,
            $requestedQuantities,
            $expiresAt,
            $rawAccessToken,
            $accessTokenHash
        ) {
            $orderItemsData = [];
            $subtotalMinor = 0;
            $lockedTicketTypes = [];

            // 3. Acquire pessimistic locks in deterministic order
            foreach ($sortedTicketTypeIds as $ticketTypeId) {
                /** @var TicketType|null $ticketType */
                $ticketType = TicketType::where('id', $ticketTypeId)
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType || $ticketType->event_id !== $event->id) {
                    throw new InvalidCheckoutException("Invalid ticket category selected.");
                }

                if ($ticketType->status !== TicketTypeStatus::ACTIVE) {
                    throw new InvalidCheckoutException("Ticket category '{$ticketType->name}' is no longer active.");
                }

                $now = Carbon::now();
                if ($ticketType->sales_starts_at && $now->isBefore($ticketType->sales_starts_at)) {
                    throw new InvalidCheckoutException("Sales for '{$ticketType->name}' have not started.");
                }
                if ($ticketType->sales_ends_at && $now->isAfter($ticketType->sales_ends_at)) {
                    throw new InvalidCheckoutException("Sales for '{$ticketType->name}' have ended.");
                }

                $requested = $requestedQuantities[$ticketTypeId];

                // Validate order quantity bounds
                if ($requested < $ticketType->min_per_order) {
                    throw new InvalidCheckoutException(
                        "Minimum order for '{$ticketType->name}' is {$ticketType->min_per_order} tickets."
                    );
                }
                if ($ticketType->max_per_order !== null && $requested > $ticketType->max_per_order) {
                    throw new InvalidCheckoutException(
                        "Maximum order for '{$ticketType->name}' is {$ticketType->max_per_order} tickets."
                    );
                }

                // Authoritative inventory calculation
                $available = $ticketType->quantity_total - $ticketType->quantity_sold - $ticketType->quantity_reserved;
                if ($available < $requested) {
                    throw new InsufficientInventoryException($ticketType->name, $requested, $available);
                }

                // Calculate item total using authoritative unit price
                $itemTotalMinor = $requested * $ticketType->price_minor;
                $subtotalMinor += $itemTotalMinor;

                $orderItemsData[] = [
                    'ticket_type' => $ticketType,
                    'quantity' => $requested,
                    'unit_price_minor' => $ticketType->price_minor,
                    'total_minor' => $itemTotalMinor,
                ];

                $lockedTicketTypes[] = [
                    'model' => $ticketType,
                    'quantity' => $requested,
                ];
            }

            // 4. Generate unique Order Number
            $orderNumber = $this->generateUniqueOrderNumber();

            // 5. Persist Order
            $order = Order::create([
                'order_number' => $orderNumber,
                'event_id' => $event->id,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'subtotal_minor' => $subtotalMinor,
                'discount_minor' => 0,
                'total_amount_minor' => $subtotalMinor,
                'currency' => $event->currency,
                'status' => OrderStatus::PENDING_PAYMENT,
                'expires_at' => $expiresAt,
                'access_token_hash' => $accessTokenHash,
            ]);

            // 6. Persist OrderItems and InventoryReservations, and update TicketType reserved counts
            foreach ($orderItemsData as $itemData) {
                /** @var TicketType $ticketType */
                $ticketType = $itemData['ticket_type'];
                $qty = $itemData['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $ticketType->id,
                    'ticket_name_snapshot' => $ticketType->name,
                    'quantity' => $qty,
                    'unit_price_minor' => $itemData['unit_price_minor'],
                    'total_minor' => $itemData['total_minor'],
                    'currency' => $event->currency,
                ]);

                InventoryReservation::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $qty,
                    'status' => ReservationStatus::ACTIVE,
                    'expires_at' => $expiresAt,
                ]);

                // Atomically increment quantity_reserved
                $ticketType->increment('quantity_reserved', $qty);
            }

            $order->load(['items', 'event']);

            return [
                'order' => $order,
                'raw_access_token' => $rawAccessToken,
                'expires_at' => $expiresAt,
            ];
        });
    }

    /**
     * Resolves an Event from provided ID or slug.
     */
    private function resolveEvent(array $data): Event
    {
        if (!empty($data['event_id'])) {
            $event = Event::find($data['event_id']);
        } elseif (!empty($data['event_slug'])) {
            $event = Event::where('slug', $data['event_slug'])->first();
        } else {
            throw new InvalidCheckoutException("Event must be identified by event_id or event_slug.");
        }

        if (!$event) {
            throw new InvalidCheckoutException("Specified event does not exist.");
        }

        return $event;
    }

    /**
     * Generates a unique, collision-resistant order number (e.g. ORD-9K4M-82PL).
     */
    private function generateUniqueOrderNumber(): string
    {
        do {
            $part1 = strtoupper(Str::random(4));
            $part2 = strtoupper(Str::random(4));
            $number = "ORD-{$part1}-{$part2}";
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}

