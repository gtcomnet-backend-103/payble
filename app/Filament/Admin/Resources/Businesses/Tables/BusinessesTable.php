<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Businesses\Tables;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Business;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

final class BusinessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('balance')
                    ->state(function (Business $record) {
                        $service = app(LedgerService::class);
                        $wallet = $service->businessReceivable($record, 'NGN');

                        return Number::currency($service->getBalance($wallet) / 100, $wallet->currency);
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('webhook_url')
                    ->searchable(),
                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('verify')
                    ->hidden(fn(Business $record) => $record->verified_at)
                    ->requiresConfirmation()
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('primary')
                    ->action(fn (Business $record) => $record->update(['verified_at' => now()])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([

                ]),
            ]);
    }
}
