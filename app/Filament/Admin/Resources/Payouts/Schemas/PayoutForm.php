<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Schemas;

use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('business_id')
                    ->relationship('business', 'name')
                    ->required(),
                Select::make('currency')
                    ->options(Currency::class)
                    ->dehydrateStateUsing(fn (Currency $state) => $state->value)
                    ->required(),
                DatePicker::make('date')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
            ]);
    }
}
