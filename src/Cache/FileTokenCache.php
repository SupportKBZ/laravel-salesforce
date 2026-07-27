<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Cache;

use Illuminate\Support\Facades\Crypt;
use SupportKBZ\SObject\Contracts\TokenCache;

/**
 * Cache fichier du token OAuth (file_put_contents + Crypt).
 */
class FileTokenCache implements TokenCache
{
    /** @var string Chemin absolu du fichier de cache */
    private string $path;

    /**
     * @param  string  $path  Chemin absolu du fichier de cache.
     */
    public function __construct(string $path)
    {
        $this->path = $path;

        $directory = dirname($path);

        if (! file_exists($directory)) {
            mkdir($directory, 0750, true);
        }
    }

    /**
     * Recupere le token OAuth depuis le fichier chiffre.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        // On initialise le fichier de cache s'il n'existe pas
        if (! file_exists($this->path)) {
            $this->put([]);

            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode(Crypt::decryptString($contents), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persiste le token OAuth dans le fichier chiffre.
     *
     * @param  array<string, mixed>  $token
     */
    public function put(array $token): void
    {
        $payload = json_encode($token);
        file_put_contents($this->path, Crypt::encryptString($payload !== false ? $payload : '[]'));
    }

    /**
     * Supprime le token OAuth du fichier (ecrase avec un tableau vide).
     */
    public function forget(): void
    {
        $this->put([]);
    }

    /**
     * Retourne le chemin absolu du fichier de cache.
     */
    public function path(): string
    {
        return $this->path;
    }
}
