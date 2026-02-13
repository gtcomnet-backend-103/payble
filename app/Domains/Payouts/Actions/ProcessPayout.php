<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

final readonly class ProcessPayout
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider,
        private LedgerServiceInterface        $ledger,
    ) {}

    public function execute(Payout $payout): Payout
    {
        if (! $payout->status->is(PayoutStatus::Processing)) {
            return $payout;
        }

        // 1. Verify OTP
        $response = $this->disbursementProvider->verify($payout->provider, $payout->provider_reference);

        if (in_array($response->status, ['success', 'failed'])) {
            return DB::transaction(function () use ($payout, $response) {
                $payout->update([
                    'status' => $response->status,
                ]);

                $this->ledger->postTransaction($payout->transaction);

                return $payout;
            });
        }

        return $payout->refresh();
    }
}
