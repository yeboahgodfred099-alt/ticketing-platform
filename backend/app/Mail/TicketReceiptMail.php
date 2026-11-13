<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $ticketUrl;

    public function __construct(
        public Order $order
    ) {
        $this->order->loadMissing(['event', 'items', 'tickets']);
        $frontendUrl = (string) config('app.frontend_url', 'http://localhost:3000');
        // We link directly using the raw access token if present, or provide the retrieval path
        $this->ticketUrl = "{$frontendUrl}/tickets/" . ($order->access_token ?? $order->order_number);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Tickets for {$this->order->event->name} (Order #{$this->order->order_number})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-receipt',
            with: [
                'order' => $this->order,
                'event' => $this->order->event,
                'tickets' => $this->order->tickets,
                'ticketUrl' => $this->ticketUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $icsContent = $this->buildIcsCalendar();

        return [
            Attachment::fromData(fn () => $icsContent, 'event-invite.ics')
                ->withMime('text/calendar'),
        ];
    }

    /**
     * Build an RFC 5545 compliant iCalendar string for calendar import.
     */
    protected function buildIcsCalendar(): string
    {
        $event = $this->order->event;
        $dtStart = $event->starts_at->utc()->format('Ymd\THis\Z');
        $dtEnd = $event->ends_at->utc()->format('Ymd\THis\Z');
        $dtStamp = now()->utc()->format('Ymd\THis\Z');
        $summary = addcslashes($event->name, ",;\\");
        $location = addcslashes($event->venue_name . ($event->venue_address ? ", " . $event->venue_address : ""), ",;\\");
        $description = addcslashes("Admission ticket for {$event->name}. Access tickets: {$this->ticketUrl}", ",;\\");

        return "BEGIN:VCALENDAR\r\n" .
            "VERSION:2.0\r\n" .
            "PRODID:-//ClevaWave Tickets//EN\r\n" .
            "CALSCALE:GREGORIAN\r\n" .
            "METHOD:PUBLISH\r\n" .
            "BEGIN:VEVENT\r\n" .
            "UID:ticket-{$event->id}@clevawave.com\r\n" .
            "DTSTAMP:{$dtStamp}\r\n" .
            "DTSTART:{$dtStart}\r\n" .
            "DTEND:{$dtEnd}\r\n" .
            "SUMMARY:{$summary}\r\n" .
            "DESCRIPTION:{$description}\r\n" .
            "LOCATION:{$location}\r\n" .
            "STATUS:CONFIRMED\r\n" .
            "END:VEVENT\r\n" .
            "END:VCALENDAR\r\n";
    }
}

