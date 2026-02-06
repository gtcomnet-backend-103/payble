<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeeConfigs\Tables;

use App\Enums\PaymentChannel;
use App\Models\FeeConfig;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

final class FeeConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->default('Platform')
                    ->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Str::of($state->value)->replace('_', ' '))
                    ->icon(fn ($state) => match ($state) {
                        PaymentChannel::Card => Heroicon::CreditCard,
                        PaymentChannel::BankTransfer => Heroicon::Banknotes
                    })
                    ->searchable(),
                TextColumn::make('min_fee')
                    ->money(fn (FeeConfig $record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('max_fee')
                    ->money(fn (FeeConfig $record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('percentage')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fixed_amount')
                    ->money(fn (FeeConfig $record) => $record->currency, divideBy: 100)
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
