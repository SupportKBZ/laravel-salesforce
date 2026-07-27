<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Concerns;

use Exception;
use SupportKBZ\SObject\Cache\FileTokenCache;
use SupportKBZ\SObject\Cache\TokenCacheFactory;
use SupportKBZ\SObject\Contracts\TokenCache;

/**
 * Authentification OAuth (password grant) avec cache du token Salesforce.
 */
trait Authenticates
{
    /** @var array<string, mixed> Donnees d'acces OAuth (access_token, instance_url, ...) */
    private array $access = [];

    /** @var array<string, mixed> Identifiants API Salesforce */
    private array $apiCredentials = [];

    /** @var array<string, mixed> Options brutes (ex: Tool->options) */
    private array $credentialOptions = [];

    /** @var bool Indique si une tentative de re-authentification est en cours */
    private bool $retry = false;

    /** @var TokenCache|null Driver de cache du token OAuth */
    private ?TokenCache $tokenCache = null;

    /**
     * Cree une instance authentifiee a partir d'options (ex: Tool->options).
     *
     * Cles acceptees (aliases Tool / config) :
     * - login_url|url → instance_url
     * - key|consumer_key → consumer_key
     * - secret|consumer_secret → consumer_secret
     * - username, password, version
     * - owner_id|owner_file → owner_id (uploads)
     * - tool_identifier → suffixe de cache (isole les tokens par outil)
     *
     * @param  array<string, mixed>  $options
     */
    public static function auth(array $options): static
    {
        return new static($options);
    }

    /**
     * Authentifie l'instance aupres de Salesforce (via cache ou password grant).
     */
    public function authenticate(): static
    {
        $auth = $this->tokenCache()->get();

        // On reutilise le token en cache s'il est encore valide
        if (! empty($auth) && array_key_exists('access_token', $auth)) {
            if (array_key_exists('expiration', $auth)) {
                if ($auth['expiration'] > time()) {
                    $this->access = $auth;

                    return $this;
                }
            } else {
                $this->access = $auth;

                return $this;
            }
        }

        // Verification de l'URL d'instance
        if (empty($this->apiCredentials['instance_url'])) {
            throw new Exception('L`URL d`instance Salesforce n`est pas configuree');
        }

        $response = $this->sendApiRequest($this->apiCredentials['instance_url'].'/services/oauth2/token', 'POST', [
            'grant_type' => 'password',
            'client_id' => $this->apiCredentials['consumer_key'],
            'client_secret' => $this->apiCredentials['consumer_secret'],
            'username' => $this->apiCredentials['username'],
            'password' => $this->apiCredentials['password'],
        ], false);

        // Si il y a une erreur, on reessaye une fois l'authentification
        if (! $this->retry && (! is_array($response) || array_key_exists('error', $response))) {
            $this->retry();
        } else {
            if (is_array($response) && isset($response['instance_url'])) {
                $this->apiCredentials['instance_url'] = $response['instance_url'];
            }

            $token = is_array($response) ? $response : [];
            $this->tokenCache()->put($token);
            $this->access = $token;
        }

        return $this;
    }

    /**
     * Retourne le chemin du fichier de cache (uniquement en mode `file`).
     *
     *
     * @throws Exception
     */
    public function getCachePath(): string
    {
        $cache = $this->tokenCache();

        if (! $cache instanceof FileTokenCache) {
            throw new Exception('getCachePath() est disponible uniquement lorsque salesforce.cache.driver vaut `file`.');
        }

        return $cache->path();
    }

    /**
     * Vide le cache du token OAuth.
     */
    public function forgetTokenCache(): void
    {
        $this->tokenCache()->forget();
    }

    /**
     * Retourne l'URL d'instance Salesforce configuree.
     */
    public function getInstanceUrl(): string
    {
        return (string) $this->apiCredentials['instance_url'];
    }

    /**
     * Retourne les options de credentials (ex: Tool->options).
     *
     * @return array<string, mixed>
     */
    public function getCredentialOptions(): array
    {
        return $this->credentialOptions;
    }

    /**
     * Retourne l'OwnerId pour les uploads ContentVersion.
     */
    public function getOwnerId(): ?string
    {
        $owner = $this->credentialOptions['owner_id']
            ?? $this->credentialOptions['owner_file']
            ?? config('salesforce.owner_id');

        return $owner !== null && $owner !== '' ? (string) $owner : null;
    }

    /**
     * Charge les identifiants Salesforce depuis les options et/ou la config.
     *
     * @param  array<string, mixed>|null  $options  Options Tool ou null pour la config.
     */
    private function setCredentials(?array $options = null): static
    {
        $this->credentialOptions = $options ?? [];

        $this->apiCredentials = [
            'instance_url' => $options['login_url'] ?? $options['url'] ?? config('salesforce.url'),
            'consumer_key' => $options['key'] ?? $options['consumer_key'] ?? config('salesforce.consumer_key'),
            'consumer_secret' => $options['secret'] ?? $options['consumer_secret'] ?? config('salesforce.consumer_secret'),
            'username' => $options['username'] ?? config('salesforce.username'),
            'password' => $options['password'] ?? config('salesforce.password'),
            'version' => $options['version'] ?? config('salesforce.version'),
        ];

        $cacheSuffix = isset($options['tool_identifier'])
            ? (string) $options['tool_identifier']
            : null;

        $this->tokenCache = TokenCacheFactory::make($cacheSuffix);

        return $this;
    }

    /**
     * Copie l'etat d'authentification d'une autre instance (hydratation des records).
     *
     * @param  static  $source
     */
    private function copyAuthenticationFrom(self $source): void
    {
        $this->access = $source->access;
        $this->apiCredentials = $source->apiCredentials;
        $this->credentialOptions = $source->credentialOptions;
        $this->tokenCache = $source->tokenCache;
        $this->retry = false;
    }

    /**
     * Retourne le driver de cache du token (lazy-init).
     */
    private function tokenCache(): TokenCache
    {
        return $this->tokenCache ??= TokenCacheFactory::make();
    }

    /**
     * Invalide le token en cache et relance l'authentification.
     */
    private function retry(): static
    {
        $this->retry = true;
        $this->tokenCache()->forget();
        $this->authenticate();

        return $this;
    }
}
