<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Businesses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled()
            ->components([
                Select::make('owner_id')
                    ->relationship('owner', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->readOnly()
                    ->email()
                    ->required(),
                TextInput::make('webhook_url')
                    ->readOnly()
                    ->url(),
            ]);
    }
}
