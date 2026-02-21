<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Tables;

use App\Domains\Payouts\Actions\AuthorizePayout;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

final class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->searchable(),
                TextColumn::make('provider.name')
                    ->searchable(),
                TextColumn::make('originator.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('provider_reference')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn (Payout $payout) => $payout->currency, 100)
                    ->sortable(),
                TextColumn::make('fee')
                    ->money(fn (Payout $payout) => $payout->currency, 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                Action::make('authorize')
                    ->visible(fn (Payout $record) => $record->status->is(PayoutStatus::Pending))
                    ->requiresConfirmation()
                    ->action(function (Payout $record, AuthorizePayout $authorizeAction) {
                        try {
                            $authorizeAction->execute($record);
                            Notification::make()->success()->title('Payout has been authorized and processing')->send();
                        } catch (Exception $exception) {
                            Log::error($exception);
                            Notification::make()->danger()->title($exception->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([

            ]);
    }
}
