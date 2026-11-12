<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticketType = $this->route('ticketType');
        return $ticketType instanceof TicketType && ($this->user()?->can('update', $ticketType) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TicketType $ticketType */
        $ticketType = $this->route('ticketType');
        $minAllowedQuantity = $ticketType ? ($ticketType->quantity_sold + $ticketType->quantity_reserved) : 0;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'quantity_total' => [
                'sometimes',
                'required',
                'integer',
                "min:{$minAllowedQuantity}",
            ],
            'min_per_order' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'gte:min_per_order'],
            'sales_starts_at' => ['nullable', 'date'],
            'sales_ends_at' => ['nullable', 'date', 'after_or_equal:sales_starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var TicketType $ticketType */
        $ticketType = $this->route('ticketType');
        $minAllowed = $ticketType ? ($ticketType->quantity_sold + $ticketType->quantity_reserved) : 0;

        return [
            'quantity_total.min' => "Total quantity cannot be reduced below {$minAllowed} (already sold or actively reserved).",
        ];
    }
}

