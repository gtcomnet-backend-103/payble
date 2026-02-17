<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Pages;

use App\Domains\Payouts\Actions\CreatePayout as CreatePayoutAction;
use App\Filament\Admin\Resources\Payouts\PayoutResource;
use App\Models\Business;
use Exception;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->action(function (array $data, CreatePayoutAction $createAction) {
                    $user = Filament::auth()->user();
                    try {
                        $createAction->execute(
                            Business::find($data['business_id']),
                            $user,
                            []
                        );
                    } catch (Exception $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                        $this->halt();
                    }
                }),
        ];
    }
}
