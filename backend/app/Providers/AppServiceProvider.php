<?php

namespace App\Providers;

use App\Modules\Companies\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request (reset between requests and queued jobs).
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface lazy loading and silently discarded attributes during development and tests.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
