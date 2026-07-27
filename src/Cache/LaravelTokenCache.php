<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Cache;

use Illuminate\Support\Facades\Cache;
use SupportKBZ\SObject\Contracts\TokenCache;

/**
 * Cache du token OAuth via le systeme Cache Laravel (CACHE_DRIVER).
 */
class LaravelTokenCache implements TokenCache
{
    /** @var string Cle de cache Laravel */
    private string $key;

    /**
     * @param  string  $key  Cle de cache Laravel.
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * Recupere le token OAuth depuis le cache Laravel.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $value = Cache::get($this->key);

        return is_array($value) ? $value : [];
    }

    /**
     * Persiste le token OAuth dans le cache Laravel.
     *
     * @param  array<string, mixed>  $token
     */
    public function put(array $token): void
    {
        // On calcule un TTL a partir de l'expiration Salesforce si disponible
        if (isset($token['expiration']) && is_numeric($token['expiration'])) {
            $ttl = max(1, (int) $token['expiration'] - time());
            Cache::put($this->key, $token, $ttl);

            return;
        }

        Cache::forever($this->key, $token);
    }

    /**
     * Supprime le token OAuth du cache Laravel.
     */
    public function forget(): void
    {
        Cache::forget($this->key);
    }
}
