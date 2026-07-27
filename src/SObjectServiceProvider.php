<?php

declare(strict_types=1);

namespace SupportKBZ\SObject;

use Illuminate\Support\ServiceProvider;
use SupportKBZ\SObject\Console\Commands\MakeSObjectCommand;

class SObjectServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/salesforce.php',
            'salesforce',
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/salesforce.php' => config_path('salesforce.php'),
        ], ['laravel-salesforce', 'laravel-salesforce-config']);

        $this->commands([
            MakeSObjectCommand::class,
        ]);
    }
}
