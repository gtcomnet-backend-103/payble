<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ledger_account_id
 * @property int $transaction_id
 * @property string|null $reference
 * @property int $amount
 * @property string $direction
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read LedgerAccount $account
 * @property-read Transaction $transaction
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereUpdatedAt($value)
 *
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
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
