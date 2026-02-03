<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:100'], // Assume minor units, min 1.00
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bearer' => ['nullable', 'string', 'in:merchant,customer,split'],
            'metadata' => ['nullable', 'array'],
            'mode' => ['nullable', 'string', 'in:test,live'],
        ];
    }
}
