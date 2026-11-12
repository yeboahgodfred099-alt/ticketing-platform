<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\EventStatus;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketTypeStatus;
use App\Enums\WebhookStatus;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_event_status_values_and_helpers(): void
    {
        $this->assertSame('draft', EventStatus::DRAFT->value);
        $this->assertSame('published', EventStatus::PUBLISHED->value);
        $this->assertSame('unpublished', EventStatus::UNPUBLISHED->value);
        $this->assertSame('cancelled', EventStatus::CANCELLED->value);
        $this->assertSame('archived', EventStatus::ARCHIVED->value);

        $this->assertTrue(EventStatus::PUBLISHED->isPurchasable());
        $this->assertFalse(EventStatus::DRAFT->isPurchasable());
        $this->assertFalse(EventStatus::CANCELLED->isPurchasable());
    }

    public function test_order_status_values_and_helpers(): void
    {
        $this->assertSame('pending_payment', OrderStatus::PENDING_PAYMENT->value);
        $this->assertSame('paid', OrderStatus::PAID->value);
        $this->assertSame('expired', OrderStatus::EXPIRED->value);
        $this->assertSame('cancelled', OrderStatus::CANCELLED->value);
        $this->assertSame('partially_refunded', OrderStatus::PARTIALLY_REFUNDED->value);
        $this->assertSame('refunded', OrderStatus::REFUNDED->value);

        $this->assertTrue(OrderStatus::PAID->isFinal());
        $this->assertFalse(OrderStatus::PENDING_PAYMENT->isFinal());
    }

    public function test_payment_status_values_and_helpers(): void
    {
        $this->assertSame('initiated', PaymentStatus::INITIATED->value);
        $this->assertSame('pending', PaymentStatus::PENDING->value);
        $this->assertSame('requires_action', PaymentStatus::REQUIRES_ACTION->value);
        $this->assertSame('success', PaymentStatus::SUCCESS->value);
        $this->assertSame('failed', PaymentStatus::FAILED->value);
        $this->assertSame('abandoned', PaymentStatus::ABANDONED->value);
        $this->assertSame('expired', PaymentStatus::EXPIRED->value);
        $this->assertSame('reversed', PaymentStatus::REVERSED->value);
        $this->assertSame('refunded', PaymentStatus::REFUNDED->value);

        $this->assertTrue(PaymentStatus::SUCCESS->isSuccessful());
        $this->assertFalse(PaymentStatus::PENDING->isSuccessful());
        $this->assertTrue(PaymentStatus::PENDING->isPending());
    }

    public function test_ticket_status_values_and_helpers(): void
    {
        $this->assertSame('active', TicketStatus::ACTIVE->value);
        $this->assertSame('checked_in', TicketStatus::CHECKED_IN->value);
        $this->assertSame('cancelled', TicketStatus::CANCELLED->value);
        $this->assertSame('refunded', TicketStatus::REFUNDED->value);

        $this->assertTrue(TicketStatus::ACTIVE->isValidForAdmission());
        $this->assertFalse(TicketStatus::CHECKED_IN->isValidForAdmission());
    }

    public function test_ticket_type_status_values(): void
    {
        $this->assertSame('active', TicketTypeStatus::ACTIVE->value);
        $this->assertSame('inactive', TicketTypeStatus::INACTIVE->value);
        $this->assertSame('archived', TicketTypeStatus::ARCHIVED->value);

        $this->assertTrue(TicketTypeStatus::ACTIVE->isAvailable());
        $this->assertFalse(TicketTypeStatus::INACTIVE->isAvailable());
    }

    public function test_reservation_status_values(): void
    {
        $this->assertSame('active', ReservationStatus::ACTIVE->value);
        $this->assertSame('converted', ReservationStatus::CONVERTED->value);
        $this->assertSame('released', ReservationStatus::RELEASED->value);
        $this->assertSame('expired', ReservationStatus::EXPIRED->value);

        $this->assertTrue(ReservationStatus::ACTIVE->holdsInventory());
        $this->assertFalse(ReservationStatus::RELEASED->holdsInventory());
    }

    public function test_webhook_status_values(): void
    {
        $this->assertSame('received', WebhookStatus::RECEIVED->value);
        $this->assertSame('queued', WebhookStatus::QUEUED->value);
        $this->assertSame('processing', WebhookStatus::PROCESSING->value);
        $this->assertSame('processed', WebhookStatus::PROCESSED->value);
        $this->assertSame('failed', WebhookStatus::FAILED->value);
        $this->assertSame('ignored', WebhookStatus::IGNORED->value);
    }

    public function test_notification_status_values(): void
    {
        $this->assertSame('queued', NotificationStatus::QUEUED->value);
        $this->assertSame('sent', NotificationStatus::SENT->value);
        $this->assertSame('failed', NotificationStatus::FAILED->value);
    }
}

