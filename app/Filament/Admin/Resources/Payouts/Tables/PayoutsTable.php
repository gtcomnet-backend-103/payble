<?php

namespace App\Filament\Admin\Resources\Payouts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->searchable(),
                TextColumn::make('provider.name')
                    ->searchable(),
                TextColumn::make('originator_type')
                    ->searchable(),
                TextColumn::make('originator_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('provider_reference')
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fee')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->badge()
                    ->searchable(),
                TextColumn::make('mode')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('reference')
                    ->searchable(),
                IconColumn::make('requires_otp')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bankAccount.id')
                    ->searchable(),
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
