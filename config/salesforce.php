<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Salesforce Instance URL
    |--------------------------------------------------------------------------
    |
    | URL de base de ton org Salesforce (login ou instance).
    |
    */

    'url' => env('SF_URL'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Password Grant Credentials
    |--------------------------------------------------------------------------
    */

    'consumer_key' => env('SF_CONSUMER_KEY'),

    'consumer_secret' => env('SF_CONSUMER_SECRET'),

    'username' => env('SF_USERNAME'),

    'password' => env('SF_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | Exemple : v59.0
    |
    */

    'version' => env('SF_VERSION', 'v59.0'),

    /*
    |--------------------------------------------------------------------------
    | Owner Id (ContentVersion uploads)
    |--------------------------------------------------------------------------
    */

    'owner_id' => env('SF_OWNER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | driver :
    | - `file`    : persiste le token chiffre via file_put_contents
    | - `default` : utilise le Cache Laravel (driver = CACHE_DRIVER)
    |
    | path : dossier du fichier (mode file uniquement). Defaut : storage/cache
    | key  : cle Cache Laravel (mode default). Defaut : salesforce.oauth_token.{env}
    |
    */

    'cache' => [
        'driver' => env('SF_CACHE_DRIVER', 'default'),
        'path' => env('SF_CACHE_PATH'),
        'key' => env('SF_CACHE_KEY'),
    ],

];
