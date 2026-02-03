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
        ];
    }
}
