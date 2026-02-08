<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeeConfigs\Pages;

use App\Filament\Admin\Resources\FeeConfigs\FeeConfigResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateFeeConfig extends CreateRecord
{
    protected static string $resource = FeeConfigResource::class;
}
