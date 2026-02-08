<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Businesses;

use App\Filament\Admin\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Admin\Resources\Businesses\Pages\EditBusiness;
use App\Filament\Admin\Resources\Businesses\Pages\ListBusinesses;
use App\Filament\Admin\Resources\Businesses\Schemas\BusinessForm;
use App\Filament\Admin\Resources\Businesses\Tables\BusinessesTable;
use App\Models\Business;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BusinessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessesTable::configure($table);
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
            'index' => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit' => EditBusiness::route('/{record}/edit'),
        ];
    }
}
