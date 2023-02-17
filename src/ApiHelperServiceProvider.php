<?php

namespace Jonathannerat\LaravelApiHelper;

use Illuminate\Support\ServiceProvider;

class ApiHelperServiceProvider extends ServiceProvider {
    public function boot() {
        $this->publishes([
            __DIR__ . '/../config' => config_path()
        ], 'config');
    }

    public function register() { }
}
