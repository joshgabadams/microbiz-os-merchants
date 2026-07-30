<?php

namespace App\Providers;

use App\Events\FinancialTransactionCreated;
use App\Events\FinancialTransactionPosted;
use App\Events\FinancialTransactionReversed;
use App\Listeners\NotifyFinancialUsers;
use App\Listeners\UpdateAuditTrail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PostFinancialTransactionToGL listens to FinancialTransactionPosted
        // only, so it's picked up by Laravel's listener auto-discovery via
        // its single type-hint. UpdateAuditTrail and NotifyFinancialUsers
        // handle a union of all three events, which auto-discovery does not
        // support, so they're registered explicitly here instead.
        foreach ([
            FinancialTransactionCreated::class,
            FinancialTransactionPosted::class,
            FinancialTransactionReversed::class,
        ] as $event) {
            Event::listen($event, UpdateAuditTrail::class);
            Event::listen($event, NotifyFinancialUsers::class);
        }
    }
}
