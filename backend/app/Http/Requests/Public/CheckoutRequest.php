<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Services\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Anonymous guest checkout
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_slug' => ['required_without:event_id', 'nullable', 'string'],
            'event_id' => ['required_without:event_slug', 'nullable', 'uuid'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:rfc,dns', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $phone = $this->input('customer_phone');
            if ($phone && !PhoneNormalizer::isValid((string) $phone)) {
                $validator->errors()->add(
                    'customer_phone',
                    'Please provide a valid Ghanaian phone number (e.g. 024XXXXXXX or +23324XXXXXXX).'
                );
            }
        });
    }
}
