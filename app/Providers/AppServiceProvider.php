<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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
        //
        $this->loadRoutes();
        Schema::defaultStringLength(191);
    }

    protected function loadRoutes()
    {
        // Ensure routes/api.php is loaded
        if (file_exists(base_path('routes/api.php'))) {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        }
    }
}
