<?php

namespace App\Filament\Admin\Resources\Payouts\Schemas;

use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('business_id')
                    ->relationship('business', 'name')
                    ->required(),
//                Select::make('provider_id')
//                    ->relationship('provider', 'name'),
//                TextInput::make('originator_type')
//                    ->required(),
//                TextInput::make('originator_id')
//                    ->required()
//                    ->numeric(),
//                TextInput::make('provider_reference'),
//                TextInput::make('amount')
//                    ->required()
//                    ->numeric(),
//                TextInput::make('fee')
//                    ->required()
//                    ->numeric()
//                    ->default(0),
//                Select::make('currency')
//                    ->options(Currency::class)
//                    ->required(),
//                Select::make('mode')
//                    ->options(PaymentMode::class)
//                    ->default('test')
//                    ->required(),
//                Select::make('status')
//                    ->options(PayoutStatus::class)
//                    ->default('pending')
//                    ->required(),
//                TextInput::make('reference')
//                    ->required(),
//                Toggle::make('requires_otp')
//                    ->required(),
//                TextInput::make('metadata'),
//                Select::make('bank_account_id')
//                    ->relationship('bankAccount', 'id'),
            ]);
    }
}
