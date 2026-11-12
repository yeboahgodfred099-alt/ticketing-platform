<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class SubmitOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reference = $this->input('reference');
        if (!is_string($reference) || empty($reference)) {
            return false;
        }

        $payment = Payment::where('reference', $reference)->with('order')->first();
        if (!$payment || !$payment->order) {
            return false;
        }

        $token = $this->header('X-Order-Token') ?? $this->input('token') ?? $this->query('token');
        if (!is_string($token) || empty($token)) {
            return false;
        }

        $providedHash = hash('sha256', $token);
        return $payment->order->access_token_hash && hash_equals($payment->order->access_token_hash, $providedHash);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string'],
            'otp' => ['required', 'string', 'min:4', 'max:10'],
        ];
    }
}

