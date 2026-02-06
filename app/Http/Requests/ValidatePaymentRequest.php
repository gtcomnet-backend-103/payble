<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ValidatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['nullable', 'string', 'min:4', 'max:6'],
            'otp' => ['nullable', 'string', 'min:4', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birthday' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
        ];
    }
}
