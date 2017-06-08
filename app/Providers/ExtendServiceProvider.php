<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ModelExtend;

class ExtendServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->singleton('extend', function(){
            return new  ModelExtend();
        });

        $this->app->bind('App\MyClass\ModelExtend', function(){
            return new ModelExtend();
        });
    }
}
