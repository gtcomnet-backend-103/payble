<?php

namespace App\Filament\Admin\Resources\FeeConfigs;

use App\Filament\Admin\Resources\FeeConfigs\Pages\CreateFeeConfig;
use App\Filament\Admin\Resources\FeeConfigs\Pages\EditFeeConfig;
use App\Filament\Admin\Resources\FeeConfigs\Pages\ListFeeConfigs;
use App\Filament\Admin\Resources\FeeConfigs\Schemas\FeeConfigForm;
use App\Filament\Admin\Resources\FeeConfigs\Tables\FeeConfigsTable;
use App\Models\FeeConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeeConfigResource extends Resource
{
    protected static ?string $model = FeeConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return FeeConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeeConfigsTable::configure($table);
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
            'index' => ListFeeConfigs::route('/'),
            'create' => CreateFeeConfig::route('/create'),
            'edit' => EditFeeConfig::route('/{record}/edit'),
        ];
    }
}
