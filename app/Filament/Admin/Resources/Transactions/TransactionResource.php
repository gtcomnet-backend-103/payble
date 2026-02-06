<?php

namespace App\Filament\Admin\Resources\Transactions;

use App\Filament\Admin\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Admin\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Admin\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Admin\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ReceiptPercent;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
        ];
    }
}
