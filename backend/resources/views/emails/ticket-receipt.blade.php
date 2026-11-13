<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Tickets for {{ $event->name }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #020617;
            color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 32px 16px;
        }
        .card {
            background-color: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        .header {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 1px solid #1e293b;
        }
        .brand {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .brand span {
            color: #10b981;
        }
        .status-badge {
            display: inline-block;
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 12px;
            margin-top: 16px;
        }
        .event-box {
            background-color: #020617;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
        }
        .event-title {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 10px 0;
        }
        .event-detail {
            font-size: 13px;
            color: #94a3b8;
            margin: 4px 0;
        }
        .cta-button {
            display: block;
            background-color: #10b981;
            color: #020617 !important;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 10px;
            margin: 28px 0;
        }
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 13px;
        }
        .tickets-table th {
            text-align: left;
            padding: 8px 0;
            color: #64748b;
            border-bottom: 1px solid #1e293b;
            font-size: 11px;
            text-transform: uppercase;
        }
        .tickets-table td {
            padding: 12px 0;
            border-bottom: 1px solid #1e293b;
            color: #f1f5f9;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: #64748b;
            margin-top: 32px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="brand">ClevaWave <span>Tickets</span></div>
                <div class="status-badge">Payment Confirmed • Tickets Issued</div>
                <h1 style="color: #ffffff; font-size: 24px; margin: 16px 0 4px 0;">You're Ready for the Event!</h1>
                <p style="color: #94a3b8; font-size: 13px; margin: 0;">Hi {{ $order->customer_name }}, here is your official ticket confirmation.</p>
            </div>

            <div class="event-box">
                <div class="event-title">{{ $event->name }}</div>
                <div class="event-detail">📅 {{ $event->starts_at->setTimezone($event->timezone)->format('D, M j, Y @ g:i A') }} ({{ $event->timezone }})</div>
                <div class="event-detail">📍 {{ $event->venue_name ?? 'Venue TBA' }}{{ $event->venue_address ? ', ' . $event->venue_address : '' }}</div>
                <div class="event-detail">🎫 Order #{{ $order->order_number }}</div>
            </div>

            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Ticket Category</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->ticket_name_snapshot }}</td>
                            <td style="text-align: center;">{{ $item->quantity }}</td>
                            <td style="text-align: right; font-weight: 600;">GH₵ {{ number_format($item->total_minor / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <a href="{{ $ticketUrl }}" class="cta-button">
                View & Present Scannable QR Tickets 🎟️
            </a>

            <div style="background-color: rgba(15, 23, 42, 0.6); border: 1px solid #1e293b; border-radius: 8px; padding: 12px; font-size: 11px; color: #94a3b8; text-align: center;">
                💡 <strong>Gate Admission Tip:</strong> Save this link or take a screenshot of your QR codes. Simply present the QR code at the entrance for instant check-in.
            </div>
        </div>

        <div class="footer">
            <p>© 2026 ClevaWave Tickets • Ghana Mobile Money Ticketing</p>
            <p>An iCalendar (.ics) invite is attached so you can add this event directly to your calendar.</p>
        </div>
    </div>
</body>
</html>

