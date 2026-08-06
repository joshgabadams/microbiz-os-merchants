<?php

namespace App\Providers;

use App\Domain\MPay\Contracts\FineractGateway;
use App\Domain\MPay\Services\FakeFineractGateway;
use App\Services\Fineract\FineractClient;
use Illuminate\Support\ServiceProvider;

class MPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            FineractGateway::class,
            $this->app->environment('local', 'testing') ? FakeFineractGateway::class : FineractClient::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/mpay'));
    }
}
