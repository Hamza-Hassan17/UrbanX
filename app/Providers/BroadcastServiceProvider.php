<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes();

        // Mobile clients authenticate with a Sanctum bearer token, not the
        // session cookie the default /broadcasting/auth route expects.
        Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api']);

        require base_path('routes/channels.php');
    }
}
