<?php

namespace App\Providers;

use App\Mail\Transport\SendGridTransport;
use Illuminate\Mail\MailManager;
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
        $this->app->make(MailManager::class)->extend('sendgrid', function () {
            return new SendGridTransport(
                (string) config('sendgrid.api_key'),
                (bool) config('sendgrid.eu_data_residency', false),
                (int) config('sendgrid.timeout', 10)
            );
        });
    }
}
