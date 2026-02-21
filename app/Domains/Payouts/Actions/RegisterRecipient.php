<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\BankAccountResolver;
use App\Enums\Currency;
use App\Models\BankAccount;
use App\Models\Business;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class RegisterRecipient
{
    public function __construct(
        private BankAccountResolver $bankAccountResolver,
    ) {}

    /**
     * @param array{
     *   account_number: string,
     *   bank_code: string,
     *   currency: string
     * } $data
     */
    public function execute(Business $business, array $data): BankAccount
    {
        Validator::make($data, [
            'account_number' => ['required', 'string', 'max:255'],
            'bank_code' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
        ])->validate();

        // Resolve account details from provider (Step 2 flow requirement)
        $account = $this->bankAccountResolver->resolveAccount(
            $data['bank_code'],
            $data['account_number']
        );

        return $business->bankAccounts()->updateOrCreate(
            [
                'account_number' => $account->accountNumber,
                'bank_code' => $account->bankCode,
            ],
            [
                'account_name' => $account->accountName,
                'currency' => $data['currency'],
                'verified_at' => now(),
            ]
        );
    }
}
