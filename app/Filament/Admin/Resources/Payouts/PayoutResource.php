<?php

namespace App\Filament\Admin\Resources\Payouts;

use App\Filament\Admin\Resources\Payouts\Pages\CreatePayout;
use App\Filament\Admin\Resources\Payouts\Pages\EditPayout;
use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Admin\Resources\Payouts\Schemas\PayoutForm;
use App\Filament\Admin\Resources\Payouts\Tables\PayoutsTable;
use App\Models\Payout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return PayoutForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
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
            'index' => ListPayouts::route('/')
        ];
    }
}
