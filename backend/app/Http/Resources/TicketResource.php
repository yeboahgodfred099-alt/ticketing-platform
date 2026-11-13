<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Services\Tickets\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $qrService = app(QrCodeService::class);
        $rawQrToken = $qrService->getRawToken($this->id, $this->ticket_number);
        $qrCodeDataUri = $qrService->generateDataUri($rawQrToken, 260);

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'category_name' => $this->orderItem?->ticket_name_snapshot ?? 'General Admission',
            'holder_name' => $this->holder_name,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'qr_code_data_uri' => $qrCodeDataUri,
            'raw_qr_token' => $rawQrToken,
        ];
    }
}

