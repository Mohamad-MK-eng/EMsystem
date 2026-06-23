<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Schema::defaultStringLength(191);

        $this->configureRateLimiting();
    }

private function configureRateLimiting(): void
{
    RateLimiter::for('checkout', function (Request $request) {
        if (!config('performance.use_rate_limiting')) {
            return Limit::none();
        }
        return Limit::perMinute(25)
            ->by($request->user()?->id ?: $request->ip())
            ->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many checkout attempts. Please wait before trying again.',
                ], 429);
            });
    });

    RateLimiter::for('products', function (Request $request) {
        if (!config('performance.use_rate_limiting')) {
            return Limit::none();
        }
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())->
            response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            });
    });

    RateLimiter::for('api', function (Request $request) {
        if (!config('performance.use_rate_limiting')) {
            return Limit::none();
        }

        return Limit::perMinute(65)->by($request->user()?->id ?: $request->ip())
            ->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            });
    });
}}
