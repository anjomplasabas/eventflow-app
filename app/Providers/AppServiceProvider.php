<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Receipts\ReceiptRenderer;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Receipts\DompdfReceiptRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            $secret = (string) config('services.payments.webhook_secret', 'local-dev-secret');

            return new FakePaymentGateway($secret);
        });

        $this->app->singleton(ReceiptRenderer::class, DompdfReceiptRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
