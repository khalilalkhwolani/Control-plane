<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Installation;
use App\Models\VendorUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Google2FA::class, fn () => new Google2FA);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'vendor_user' => VendorUser::class,
            'customer' => Customer::class,
            'installation' => Installation::class,
        ]);

        RateLimiter::for('control-login', function (Request $request) {
            return Limit::perMinute(10)->by(strtolower((string) $request->input('email', '')).'|'.$request->ip());
        });
    }
}
