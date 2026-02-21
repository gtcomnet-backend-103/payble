<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Contracts\Recordable;
use App\Enums\TransactionStatus;
use App\Models\Transaction;

final class RecordTransaction
{
    public function __construct() {}

    /**
     * Create a transaction record from a Recordable source.
     */
    public function execute(Recordable $source): Transaction
    {
        $data = $source->toTransactionData();

        return Transaction::create([
            'business_id' => $data->businessId,
            'source_type' => get_class($source),
            'source_id' => $source->id,
            'reference' => $data->reference,
            'amount' => $data->amount,
            'fee' => $data->fee,
            'currency' => $data->currency,
            'status' => TransactionStatus::Pending,
            'mode' => $data->mode,
            'metadata' => $data->metadata,
            'provider_reference' => $source->provider_reference ?? null,
        ]);
    }
}
