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

        // Agent/POS registry tables -- main database, just kept in their own
        // folder (like mpay/) so they don't get lost in the flat migrations list.
        $this->loadMigrationsFrom(database_path('migrations/agent'));
    }
}
