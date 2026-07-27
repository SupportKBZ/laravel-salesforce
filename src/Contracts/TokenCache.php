<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Contracts;

/**
 * Contrat de persistence du token OAuth Salesforce.
 */
interface TokenCache
{
    /**
     * Recupere le token OAuth depuis le cache.
     *
     * @return array<string, mixed>
     */
    public function get(): array;

    /**
     * Persiste le token OAuth dans le cache.
     *
     * @param  array<string, mixed>  $token
     */
    public function put(array $token): void;

    /**
     * Supprime le token OAuth du cache.
     */
    public function forget(): void;
}
