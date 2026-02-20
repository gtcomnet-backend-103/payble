<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Models\LedgerBatch;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LedgerTransactionManager
{
    public function __construct(
        private readonly LedgerService $service,
        private readonly Transaction $transaction,
        private readonly string $batchName = 'default',
    ) {}

    public function name(string $name): self
    {
        return new self($this->service, $this->transaction, $name);
    }

    /**
     * @param  LedgerEntryDTO[]  $entries
     */
    public function entries(array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        // 1. Structural Validation (Must sum to zero)
        $this->validateBalancing($entries);

        DB::transaction(function () use ($entries) {
            // 2. Start/Retrieve Batch
            $batch = $this->service->startBatch($this->transaction, $this->batchName);

            // Re-fetch with lock to ensure idempotency is strictly enforced
            $lockedBatch = LedgerBatch::where('id', $batch->id)->lockForUpdate()->first();

            if ($lockedBatch->isPosted()) {
                return;
            }

            foreach ($entries as $dto) {
                // 3. Persist the Ledger Entry (The immutable audit trail)
                $entry = LedgerEntry::create([
                    'ledger_batch_id' => $lockedBatch->id,
                    'ledger_account_id' => $dto->account->id,
                    'transaction_id' => $this->transaction->id,
                    'reference' => $this->transaction->reference,
                    'amount' => $dto->amount,
                    'direction' => $dto->direction,
                ]);

                // 4. Atomic Snapshot Update (The fast balance lookup)
                $mathAmount = $dto->direction->value === 'debit'
                    ? $dto->amount
                    : -$dto->amount;

                $this->service->incrementSnapshot($dto->account, $mathAmount, $entry->id);
            }

            // 5. Finalize the batch
            $lockedBatch->markPosted();
        });
    }

    private function validateBalancing(array $entries): void
    {
        $balance = 0;
        foreach ($entries as $entry) {
            $balance += ($entry->direction->value === 'debit')
                ? $entry->amount
                : -$entry->amount;
        }

        if ($balance !== 0) {
            throw new RuntimeException("Ledger unbalanced. Deviation: {$balance} units.");
        }
    }
}
