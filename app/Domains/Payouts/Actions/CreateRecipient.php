<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\BankAccountResolver;
use App\Enums\Currency;
use App\Models\BankAccount;
use App\Models\Business;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CreateRecipient
{
    public function __construct(
        private BankAccountResolver $bankAccountResolver,
    )
    {
    }

    public function execute(Business $business, array $data): BankAccount
    {
        Validator::make($data, [
            'account_number' => ['required', 'string', 'max:255'],
            'bank_code' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
        ])->validate();

        $account = $this->bankAccountResolver->resolveAccount(
            $data['bank_code'],
            $data['account_number']
        );

        $bankAccount =  $business->bankAccount()->create([
            'account_number' => $account->accountNumber,
            'account_name' => $account->accountName,
            'bank_code' => $account->bankCode,
            'currency' => $data['currency'],
            'verified_at' => now()
        ]);

        $business->bankAccount()->associate($bankAccount);
        $business->save();
        return $bankAccount;
    }
}
