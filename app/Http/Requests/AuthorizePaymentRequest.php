<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AuthorizePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(PaymentChannel::class)],

            // Card Validation Rules
            'card' => ['required_if:channel,card', 'array'],
            'card.number' => ['required_if:channel,card', 'string', 'min:12', 'max:19'],
            'card.cvv' => ['required_if:channel,card', 'string', 'min:3', 'max:4'],
            'card.expiry_month' => ['required_if:channel,card', 'string', 'size:2'],
            'card.expiry_year' => ['required_if:channel,card', 'string', 'size:2'],

            // Bank Transfer Validation Rules (Should not send data, as provider generates account)
            'bank_transfer' => ['missing'],
        ];
    }
}
