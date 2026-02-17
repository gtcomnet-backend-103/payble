<?php

namespace App\Domains\Payouts\Contracts;

use App\Domains\Payouts\DataTransferObjects\BankAccountDetails;

interface BankAccountResolver
{
    public function resolveAccount(string $bankCode, string $accountNumber): BankAccountDetails;
}
