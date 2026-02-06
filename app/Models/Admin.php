<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

class Admin extends \Illuminate\Foundation\Auth\User implements FilamentUser
{

    public function canAccessPanel(Panel $panel): bool
    {
        return true; //TODO: implement this
    }
}
