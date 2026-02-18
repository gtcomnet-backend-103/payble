<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ledger_account_id
 * @property int|null $transaction_id
 * @property string|null $reference
 * @property int $amount
 * @property EntryDirection $direction
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $ledger_batch_id
 * @property-read \App\Models\Account $account
 * @property-read \App\Models\LedgerBatch|null $batch
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereLedgerBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereUpdatedAt($value)
 * @mixin \Eloquent
 */
final class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_account_id',
        'transaction_id',
        'reference',
        'amount',
        'direction',
        'ledger_batch_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ledger_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LedgerBatch::class, 'ledger_batch_id');
    }

    public function casts(): array
    {
        return [
            'direction' => EntryDirection::class,
            'amount' => 'integer',
        ];
    }
}
