<?php

declare(strict_types=1);

namespace App\Filament\Pages\Tenancy;

use App\Models\Business;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Pages\Tenancy\RegisterTenant;

final class RegisterBusiness extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Onboard Business';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function handleRegistration(array $data): Business
    {
        $business = Business::create([
            ...$data,
            'owner_id' => Filament::auth()->id(),
        ]);

        $business->users()->attach(Filament::auth()->user());

        // Create initial ledger accounts
        app(\App\Domains\Ledger\Actions\CreateLedgerAccounts::class)->execute($business);

        return $business;
    }
}
