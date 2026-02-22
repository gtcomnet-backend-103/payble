<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Enums\AuthorizationStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

final class ProcessPayout
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider,
        private LedgerPostingServiceInterface $ledger,
    ) {}

    public function execute(Payout $payout): Payout
    {
        if (! in_array($payout->status, [
            PayoutStatus::Processing,
            PayoutStatus::Unknown,
            PayoutStatus::ReconciliationRequired,
        ], true)) {
            return $payout;
        }

        $response = $this->disbursementProvider->verify($payout->provider, $payout->provider_reference);

        if ($response->status->isFinal()) {
            return DB::transaction(function () use ($payout, $response): Payout {
                if ($response->status === AuthorizationStatus::Success) {
                    $payout->update(['status' => PayoutStatus::Success]);

                    if ($payout->type === \App\Enums\PayoutType::Payout) {
                        $this->ledger->postTransaction($payout->transaction);
                    }
                }

                if ($response->status === AuthorizationStatus::Failed) {
                    $payout->update(['status' => PayoutStatus::Failed]);
                    $this->ledger->reverse($payout->transaction);
                }

                return $payout->refresh();
            });
        }

        return $payout->refresh();
    }
}
