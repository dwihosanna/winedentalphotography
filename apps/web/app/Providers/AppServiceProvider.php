<?php

namespace App\Providers;

use App\Support\CurrentWorkspace;
use App\Support\RetryingMySqlConnector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: dikosongkan lagi di setiap request dan job antrian.
        $this->app->scoped(CurrentWorkspace::class);

        // Batas koneksi MySQL di hosting: coba ulang sebentar alih-alih langsung error 500.
        $this->app->bind('db.connector.mysql', RetryingMySqlConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::resourceVerbs(['create' => 'baru', 'edit' => 'ubah']);
    }
}
