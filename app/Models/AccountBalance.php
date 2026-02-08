<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
