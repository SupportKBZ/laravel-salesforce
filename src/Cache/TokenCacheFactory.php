<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Cache;

use InvalidArgumentException;
use SupportKBZ\SObject\Contracts\TokenCache;

/**
 * Fabrique le driver de cache du token selon la configuration.
 */
class TokenCacheFactory
{
    /**
     * Cree l'implementation de TokenCache selon salesforce.cache.driver.
     *
     * - `file`    : fichier local (file_put_contents + Crypt)
     * - `default` : Cache Laravel (driver defini par CACHE_DRIVER)
     *
     * @throws InvalidArgumentException
     */
    public static function make(): TokenCache
    {
        $driver = (string) config('salesforce.cache.driver', 'default');

        return match ($driver) {
            'file' => new FileTokenCache(self::resolveFilePath()),
            'default' => new LaravelTokenCache(self::resolveCacheKey()),
            default => throw new InvalidArgumentException(
                "Driver de cache Salesforce invalide [{$driver}]. Utilise `file` ou `default`.",
            ),
        };
    }

    /**
     * Resolue le chemin du fichier de cache (mode file).
     */
    public static function resolveFilePath(): string
    {
        $configuredPath = config('salesforce.cache.path');
        $directory = $configuredPath
            ? rtrim((string) $configuredPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
            : storage_path('cache'.DIRECTORY_SEPARATOR);

        return $directory.'sf_token_'.config('app.env').'.json';
    }

    /**
     * Resolue la cle de cache Laravel (mode default).
     */
    public static function resolveCacheKey(): string
    {
        $configuredKey = config('salesforce.cache.key');

        if (is_string($configuredKey) && $configuredKey !== '') {
            return $configuredKey;
        }

        return 'salesforce.oauth_token.'.config('app.env');
    }
}
