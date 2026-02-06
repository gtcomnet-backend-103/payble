<?php

namespace App\Filament\Admin\Resources\Providers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('identifier')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_healthy')
                    ->required(),
                Textarea::make('supported_channels')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
