<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Payouts\Actions\AuthorizeLedgerTransfer;
use App\Domains\Payouts\Actions\GetBankList;
use App\Domains\Payouts\Actions\InitiateLedgerTransfer;
use App\Domains\Payouts\Actions\RegisterRecipient;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class TransferController
{
    public function __construct(
        private GetBankList $getBankList,
        private RegisterRecipient $registerRecipient,
        private InitiateLedgerTransfer $initiateLedgerTransfer,
        private AuthorizeLedgerTransfer $authorizeLedgerTransfer,
    ) {}

    public function banks(Request $request): JsonResponse
    {
        $business = $this->getBusiness($request);

        if (! $business) {
            return response()->json(['message' => 'Active business not found.'], 403);
        }

        return response()->json([
            'data' => $this->getBankList->execute($business),
        ]);
    }

    public function registerRecipient(Request $request): JsonResponse
    {
        $business = $this->getBusiness($request);

        if (! $business) {
            return response()->json(['message' => 'Active business not found.'], 403);
        }

        $bankAccount = $this->registerRecipient->execute($business, $request->all());

        return response()->json([
            'message' => 'Recipient registered successfully.',
            'data' => [
                'id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->account_number,
                'bank_code' => $bankAccount->bank_code,
            ],
        ]);
    }

    public function initiate(Request $request): PayoutResource|JsonResponse
    {
        $business = $this->getBusiness($request);

        if (! $business) {
            return response()->json(['message' => 'Active business not found.'], 403);
        }

        $user = $request->user();

        $transfer = $this->initiateLedgerTransfer->execute($business, $user, $request->all());

        return PayoutResource::make($transfer);
    }

    public function authorize(string $reference, Request $request): PayoutResource|JsonResponse
    {
        try {
            $transfer = Payout::where('reference', $reference)->firstOrFail();

            $authorizedTransfer = $this->authorizeLedgerTransfer->execute($transfer);

            return PayoutResource::make($authorizedTransfer);
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['reference' => $reference]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function getBusiness(Request $request): ?\App\Models\Business
    {
        $actor = $request->user();

        return $actor instanceof \App\Models\Business ? $actor : $actor?->businesses()?->first();
    }
}
