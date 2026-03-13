<?php

namespace WardTech\ImageToolkit;

use Illuminate\Support\ServiceProvider;
use WardTech\ImageToolkit\Commands\OptimizeImages;

class ImageToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/image-toolkit.php', 'image-toolkit');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'image-toolkit');

        if ($this->app->runningInConsole()) {
            $this->commands([
                OptimizeImages::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/image-toolkit.php' => config_path('image-toolkit.php'),
            ], 'image-toolkit-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/image-toolkit'),
            ], 'image-toolkit-views');

            $this->publishes([
                __DIR__ . '/../resources/js/react' => resource_path('js/vendor/image-toolkit/react'),
            ], 'image-toolkit-react');

            $this->publishes([
                __DIR__ . '/../resources/js/vue' => resource_path('js/vendor/image-toolkit/vue'),
            ], 'image-toolkit-vue');
        }
    }
}
