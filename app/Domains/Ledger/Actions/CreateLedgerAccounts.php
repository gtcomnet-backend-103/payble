<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Models\Business;
use App\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerService;

final class CreateLedgerAccounts
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(Business $business): void
    {
        $accounts = [
            ['name' => 'Wallet', 'type' => 'asset', 'slug' => 'wallet'],
            ['name' => 'Fees', 'type' => 'revenue', 'slug' => 'fees'],
            ['name' => 'Collections', 'type' => 'asset', 'slug' => 'collections'],
            ['name' => 'Payable', 'type' => 'liability', 'slug' => 'payable'],
        ];

        foreach ($accounts as $account) {
            $this->ledgerService->getOrCreateAccount(
                $business,
                $account['slug'],
                $account['type'],
                $account['name'],
                'NGN'
            );
        }
    }
}
