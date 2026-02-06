<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel()->ignoring(\App\Providers\Filament\AdminPanelProvider::class);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();
