<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Services\DisbursementService;
use App\Enums\Currency;
use App\Models\BankAccount;
use App\Models\Business;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateRecipient
{
    public function __construct(
        private readonly DisbursementService $disbursementService,
    ) {}

    public function execute(Business $business, array $data): BankAccount
    {
        Validator::make($data, [
            'account_number' => ['required', 'string', 'max:255'],
            'bank_code' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
        ])->validate();

        $data = $this->disbursementService->validateBankAccount($data['account_number'], $data['bank_code']);

        return $business->bankAccount()->create([
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'bank_code' => $data['bank_code'],
            'currency' => $data['currency'],
            'verified_at' => now(),
        ]);
    }
}
