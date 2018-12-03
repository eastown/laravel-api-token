<?php

namespace Eastown\ApiToken\Providers;

use Illuminate\Support\ServiceProvider;

class TokenServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/api_token.php' => config_path('api_token.php'),
        ]);
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');
    }

}