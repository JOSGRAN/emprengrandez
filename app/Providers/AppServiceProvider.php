<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Payment;
use App\Observers\ExpenseObserver;
use App\Observers\PaymentObserver;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
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
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch->locales(['es', 'en']);
        });

        Payment::observe(PaymentObserver::class);
        Expense::observe(ExpenseObserver::class);
    }
}
