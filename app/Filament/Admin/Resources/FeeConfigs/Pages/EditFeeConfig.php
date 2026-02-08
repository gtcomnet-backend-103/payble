<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeeConfigs\Pages;

use App\Filament\Admin\Resources\FeeConfigs\FeeConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditFeeConfig extends EditRecord
{
    protected static string $resource = FeeConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
