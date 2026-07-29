<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MPayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/mpay'));
    }
}
