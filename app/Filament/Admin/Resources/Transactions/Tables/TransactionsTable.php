<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Tables;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency, 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('mode')
                    ->badge()
                    ->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->searchable(),
                TextColumn::make('fees')
                    ->money(fn ($record) => $record->currency, 100)
                    ->sortable(),
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
                SelectFilter::make('business.name')
                    ->relationship('business', 'name')
                    ->getSearchResultsUsing(fn (string $search): array => Business::query()
                        ->where('name', 'like', "%{$search}%")
                        ->where('email', 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->preload()
                    ->searchable(),
                SelectFilter::make('mode')
                    ->default(PaymentMode::Live)
                    ->options(PaymentMode::class),
                SelectFilter::make('status')
                    ->default(TransactionStatus::Success)
                    ->options(TransactionStatus::class),
            ])
            ->recordActions([
            ])
            ->toolbarActions([
            ]);
    }
}
