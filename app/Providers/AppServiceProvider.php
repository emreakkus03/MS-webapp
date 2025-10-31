<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (function_exists('ini_set')) {
            ini_set('upload_max_filesize', '256M');
            ini_set('post_max_size', '256M');
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '300');
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
