<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeeConfigs\Schemas;

use App\Enums\PaymentChannel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class FeeConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('business_id')
                    ->relationship('business', 'name'),
                Select::make('channel')
                    ->options(PaymentChannel::class)
                    ->required(),
                TextInput::make('min_fee')
                    ->required()
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->dehydrateStateUsing(fn ($state) => $state * 100)
                    ->numeric()
                    ->default(0),
                TextInput::make('max_fee')
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->dehydrateStateUsing(fn ($state) => $state * 100)
                    ->numeric(),
                TextInput::make('percentage')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('fixed_amount')
                    ->required()
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->dehydrateStateUsing(fn ($state) => $state * 100)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->required(),
            ]);
    }
}
