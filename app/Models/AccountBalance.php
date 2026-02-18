<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ledger_account_id
 * @property int $balance
 * @property int $last_entry_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereLastEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
final class AccountBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_account_id',
        'balance',
        'last_entry_id',
    ];

    protected $casts = [
        'balance' => 'integer',
        'last_entry_id' => 'integer',
    ];
}
