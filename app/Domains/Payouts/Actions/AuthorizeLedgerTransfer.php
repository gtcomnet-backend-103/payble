<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Enums\AuthorizationStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AuthorizeLedgerTransfer
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider,
        private LedgerPostingServiceInterface $ledgerPostingService,
    ) {}

    public function execute(Payout $transfer): Payout
    {
        if ($transfer->status !== PayoutStatus::Pending) {
            throw ValidationException::withMessages([
                'transfer' => "Transfer is in {$transfer->status->value} state and cannot be authorized.",
            ]);
        }

        /** @var \App\Models\Provider $provider */
        $provider = $this->disbursementProvider->provider($transfer->mode);

        $netAmount = $transfer->amount - $transfer->fee;

        $response = $this->disbursementProvider->transfer(
            $provider,
            $transfer->reference,
            $transfer->bankAccount->account_number,
            $transfer->bankAccount->bank_code,
            $netAmount
        );

        return DB::transaction(function () use ($transfer, $provider, $response) {
            $transfer->update([
                'provider_id' => $provider->id,
                'provider_reference' => $response->providerReference,
                'status' => match ($response->status) {
                    AuthorizationStatus::Success => PayoutStatus::Success,
                    AuthorizationStatus::Failed => PayoutStatus::Failed,
                    default => PayoutStatus::Processing,
                },
            ]);

            if ($response->status === AuthorizationStatus::Success) {
                $this->ledgerPostingService->postTransaction($transfer->transaction);
            }

            if ($response->status === AuthorizationStatus::Failed) {
                $this->ledgerPostingService->reverse($transfer->transaction);
            }

            // Note: If status is Pending/Processing, the reservation remains.
            // A separate verification job or webhook will finalize it.

            return $transfer->refresh();
        });
    }
}
