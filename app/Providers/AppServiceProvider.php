<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Team;

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

        Gate::define('view-logs', function ($user) {

            // HIER VUL JE JOUW INLOGNAAM IN
            // Bijvoorbeeld: 'Novik', 'Admin', 'Developer'
            $developers = [
                'Developer'
            ];

            return in_array($user->name, $developers);
        });
    }


    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
