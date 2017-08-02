<?php

namespace DeveoDK\CoreApiDocGenerator;

use Illuminate\Support\ServiceProvider;
use DeveoDK\CoreApiDocGenerator\Commands\UpdateDocumentation;
use DeveoDK\CoreApiDocGenerator\Commands\GenerateDocumentation;

class CoreApiDocGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'apidoc');

        $this->publishes([
            __DIR__.'/../../resources/lang' => $this->resourcePath('lang'),
        ]);
    }

    /**
     * Register the API doc commands.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('coreapidoc.generate', function () {
            return new GenerateDocumentation();
        });
        $this->app->singleton('coreapidoc.update', function () {
            return new UpdateDocumentation();
        });
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__  . '/routes.php');

        $this->commands([
            'coreapidoc.generate',
            'coreapidoc.update',
        ]);
    }

    /**
     * Return a fully qualified path to a given file.
     *
     * @param string $path
     *
     * @return string
     */
    public function resourcePath($path = '')
    {
        return app()->basePath().'/resources'.($path ? '/'.$path : $path);
    }
}
