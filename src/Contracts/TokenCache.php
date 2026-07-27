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
     * @param  string|null  $suffix  Suffixe optionnel (ex: tool_identifier) pour isoler les tokens.
     *
     * @throws InvalidArgumentException
     */
    public static function make(?string $suffix = null): TokenCache
    {
        $driver = (string) config('salesforce.cache.driver', 'default');

        return match ($driver) {
            'file' => new FileTokenCache(self::resolveFilePath($suffix)),
            'default' => new LaravelTokenCache(self::resolveCacheKey($suffix)),
            default => throw new InvalidArgumentException(
                "Driver de cache Salesforce invalide [{$driver}]. Utilise `file` ou `default`.",
            ),
        };
    }

    /**
     * Resolue le chemin du fichier de cache (mode file).
     *
     * @param  string|null  $suffix
     */
    public static function resolveFilePath(?string $suffix = null): string
    {
        $configuredPath = config('salesforce.cache.path');
        $directory = $configuredPath
            ? rtrim((string) $configuredPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
            : storage_path('cache'.DIRECTORY_SEPARATOR);

        $name = 'sf_token_'.config('app.env');
        if ($suffix !== null && $suffix !== '') {
            $name .= '_'.$suffix;
        }

        return $directory.$name.'.json';
    }

    /**
     * Resolue la cle de cache Laravel (mode default).
     *
     * @param  string|null  $suffix
     */
    public static function resolveCacheKey(?string $suffix = null): string
    {
        $configuredKey = config('salesforce.cache.key');

        if (is_string($configuredKey) && $configuredKey !== '') {
            return $suffix ? $configuredKey.'.'.$suffix : $configuredKey;
        }

        $key = 'salesforce.oauth_token.'.config('app.env');

        return $suffix ? $key.'.'.$suffix : $key;
    }
}
