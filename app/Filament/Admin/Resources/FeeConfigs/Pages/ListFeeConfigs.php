<?php

namespace App\Filament\Admin\Resources\FeeConfigs\Pages;

use App\Filament\Admin\Resources\FeeConfigs\FeeConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeeConfigs extends ListRecords
{
    protected static string $resource = FeeConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
