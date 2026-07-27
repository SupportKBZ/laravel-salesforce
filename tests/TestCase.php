<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use SupportKBZ\SObject\SObjectServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Enregistre les providers du package.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SObjectServiceProvider::class,
        ];
    }

    /**
     * Configure l'environnement de test.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.debug', true);
        $app['config']->set('app.env', 'testing');
        $app['config']->set('cache.default', 'array');

        $app['config']->set('salesforce', [
            'url' => 'https://test.salesforce.com',
            'consumer_key' => 'test-consumer-key',
            'consumer_secret' => 'test-consumer-secret',
            'username' => 'user@example.com',
            'password' => 'secret',
            'version' => 'v59.0',
            'owner_id' => '005OWNER',
            'cache' => [
                'driver' => 'file',
                'path' => storage_path('framework/cache'),
                'key' => 'salesforce.oauth_token.testing',
            ],
        ]);
    }
}
