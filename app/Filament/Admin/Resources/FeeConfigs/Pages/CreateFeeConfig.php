<?php

namespace App\Filament\Admin\Resources\FeeConfigs\Pages;

use App\Filament\Admin\Resources\FeeConfigs\FeeConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeeConfig extends CreateRecord
{
    protected static string $resource = FeeConfigResource::class;
}
