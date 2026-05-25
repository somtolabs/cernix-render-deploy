<?php

namespace App\Providers;

use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use App\Services\VerificationService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CryptoService::class);
        $this->app->singleton(MockSISService::class);
        $this->app->singleton(QrTokenService::class);
        $this->app->singleton(VerificationService::class);
        $this->app->singleton(RemitaService::class, fn () => new RemitaService(new Client()));
        $this->app->singleton(RegistrationService::class, fn ($app) => new RegistrationService(
            $app->make(MockSISService::class),
            $app->make(RemitaService::class),
            $app->make(CryptoService::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
