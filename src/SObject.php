<?php

declare(strict_types=1);

namespace SupportKBZ\SObject;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use SupportKBZ\SObject\Concerns\Authenticates;
use SupportKBZ\SObject\Concerns\BuildsWhereClauses;
use SupportKBZ\SObject\Concerns\UploadsAndDownloads;

/**
 * Classe de base pour interagir avec l'API Salesforce.
 *
 * Fournit les operations CRUD (find, get, insert, update, save) sur un objet
 * Salesforce defini par $table et $fillable. L'authentification OAuth est
 * geree automatiquement via le trait Authenticates.
 *
 * Etendre cette classe dans ton application pour chaque sObject :
 *
 * ```php
 * class Lead extends SObject
 * {
 *     protected string $table = 'Lead';
 *     protected array $fillable = ['Id', 'Email', 'FirstName', 'LastName'];
 *     protected array $map = ['Id' => 'id', 'Email' => 'email'];
 * }
 * ```
 *
 * @implements Arrayable<string, mixed>
 */
class SObject implements Arrayable
{
    use Authenticates;
    use BuildsWhereClauses;
    use UploadsAndDownloads;

    /** @var string Nom de la cle primaire de l'objet Salesforce */
    protected string $primaryKey = 'Id';

    /** @var string Nom de l'objet Salesforce cible (ex: Lead, Contact) */
    protected string $table = '';

    /** @var string[] Champs autorises en lecture/ecriture */
    protected array $fillable = [];

    /** @var array<string, string> Correspondance champ Salesforce → nom mappe */
    protected array $map = [];

    /** @var array<string, mixed> Champs originaux retournes par l'API */
    protected array $original = [];

    /** @var array<string, mixed> Champs modifies localement */
    protected array $changed = [];

    /** @var bool Lance une exception en cas d'erreur API */
    protected bool $throwOnError = false;

    /**
     * Initialise les credentials et authentifie l'instance.
     */
    public function __construct()
    {
        $this->throwOnError = (bool) config('app.debug');
        $this->setCredentials()->auth();
    }

    /**
     * Recupere la valeur d'un champ (original ou mappe).
     *
     * @param  string  $name  Nom Salesforce ou nom mappe.
     */
    public function __get(string $name): mixed
    {
        if (! array_key_exists($name, $this->original)) {
            $name = array_search($name, $this->map, true);

            if ($name === false) {
                return null;
            }
        }

        return $this->changed[$name] ?? $this->original[$name] ?? null;
    }

    /**
     * Definit la valeur d'un champ (original ou mappe).
     *
     * @param  string  $name  Nom Salesforce ou nom mappe.
     * @param  mixed  $value  Nouvelle valeur.
     */
    public function __set(string $name, mixed $value): void
    {
        // On recupere le nom du champ original a partir du nom du champ map
        if (! array_key_exists($name, $this->original)) {
            $name = array_search($name, $this->map, true);

            if ($name === false) {
                return;
            }
        }

        // On ne fait rien si la valeur est la meme que l'original
        if (array_key_exists($name, $this->original) && $this->original[$name] === $value) {
            if (array_key_exists($name, $this->changed)) {
                unset($this->changed[$name]);
            }

            return;
        }

        $this->changed[$name] = $value;
    }

    /**
     * Indique si un champ a une valeur non nulle.
     *
     * @param  string  $name  Nom Salesforce ou nom mappe.
     */
    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /**
     * Retourne les champs originaux merges avec les modifications.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->original, $this->changed);
    }

    /**
     * Recupere le premier enregistrement correspondant a un critere.
     *
     * Suit la meme convention 1/2/3 parametres que where() :
     * - 1 param  : `find('value')`           → cherche sur Id avec `=`
     * - 2 params : `find('col', 'value')`     → cherche sur col avec `=`
     * - 3 params : `find('col', '!=', 'val')` → cherche sur col avec l'operateur donne
     *
     * @param  mixed  $value  Valeur (1 param), ou nom de colonne (2-3 params).
     * @param  mixed  $operatorOrValue  Operateur ou valeur selon le nombre de params.
     * @param  mixed  $search  Valeur finale (3 params uniquement).
     */
    public function find(mixed $value, mixed $operatorOrValue = null, mixed $search = null): ?static
    {
        if ($operatorOrValue === null) {
            return $this->where('Id', $value)->first();
        }

        return $this->where($value, $operatorOrValue, $search)->first();
    }

    /**
     * Recupere le premier enregistrement selon les clauses WHERE definies.
     *
     * @return static|null Premier enregistrement trouve, ou null.
     */
    public function first(): ?static
    {
        $result = $this->get(1);

        if ($result && $result->count() > 0) {
            return $result->first();
        }

        return null;
    }

    /**
     * Recupere le dernier enregistrement selon les clauses WHERE definies.
     *
     * @return static|null Dernier enregistrement trouve, ou null.
     */
    public function last(): ?static
    {
        $result = $this->get();

        if ($result && $result->count() > 0) {
            return $result->last();
        }

        return null;
    }

    /**
     * Recupere une liste d'enregistrements selon les clauses WHERE definies.
     *
     * La liste des conditions WHERE est reinitialisee apres chaque appel.
     *
     * @param  int  $limit  Nombre maximum d'enregistrements retournes (defaut : 100).
     * @return Collection<int, static>|null Collection d'enregistrements, ou null si aucun resultat.
     */
    public function get(int $limit = 100): ?Collection
    {
        $where = $this->buildWhereClause();
        $soql = str_replace(' ', '+',
            'SELECT '.implode(', ', $this->fillable).
            ' FROM '.addslashes($this->table).
            ($where ? ' WHERE '.$where : '').
            ' LIMIT '.$limit,
        );

        $result = $this->sendApiRequest($this->apiUrl('query/?q='.$soql));
        $this->where = [];

        if ($this->throwOnError && is_array($result) && array_key_exists(0, $result) && array_key_exists('errorCode', $result[0])) {
            throw new Exception($result[0]['errorCode'].' - '.$result[0]['message']);
        }

        if (! is_array($result) || ! array_key_exists('records', $result) || empty($result['records'])) {
            return null;
        }

        return $this->compileRecords($result['records']);
    }

    /**
     * Cree un nouvel enregistrement dans Salesforce.
     *
     * Les cles de $data peuvent etre des noms Salesforce ou des valeurs de $map
     * (noms mappes) ; elles sont automatiquement resolues avant l'envoi.
     *
     * @param  array<string, mixed>  $data  Donnees a inserer (cle => valeur).
     * @return mixed Reponse de l'API (contient l'Id cree en cas de succes).
     */
    public function insert(array $data): mixed
    {
        return $this->sendApiRequest($this->apiUrl('sobjects/'.$this->table), 'POST', $this->resolveDataKeys($data));
    }

    /**
     * Met a jour un enregistrement existant dans Salesforce.
     *
     * Les cles de $data peuvent etre des noms Salesforce ou des valeurs de $map
     * (noms mappes) ; elles sont automatiquement resolues avant l'envoi.
     *
     * @param  string  $id  Identifiant Salesforce de l'enregistrement.
     * @param  array<string, mixed>  $data  Champs a mettre a jour (cle => valeur).
     * @return mixed Reponse de l'API (vide en cas de succes HTTP 204).
     */
    public function update(string $id, array $data): mixed
    {
        return $this->sendApiRequest($this->apiUrl('sobjects/'.$this->table.'/'.$id), 'PATCH', $this->resolveDataKeys($data));
    }

    /**
     * Resolue les cles de $data via $map (nom mappe → champ Salesforce).
     *
     * @param  array<string, mixed>  $data  Tableau de donnees brut.
     * @return array<string, mixed> Tableau avec les cles resolues.
     */
    private function resolveDataKeys(array $data): array
    {
        if (empty($this->map)) {
            return $data;
        }

        $resolved = [];
        foreach ($data as $key => $value) {
            $sfKey = array_search($key, $this->map, true);
            $resolved[$sfKey !== false ? $sfKey : $key] = $value;
        }

        return $resolved;
    }

    /**
     * Sauvegarde les changements dans Salesforce (insert ou update).
     *
     *
     * @throws Exception
     */
    public function save(): static
    {
        $id = $this->{$this->primaryKey};

        if (empty($id)) {
            $response = $this->insert($this->toArray());

            if (empty($response) || ! array_key_exists('id', $response)) {
                throw new Exception('Failed to insert record');
            }

            $this->original[$this->primaryKey] = $response['id'];
            $this->original = array_merge($this->original, $this->changed);
            $this->changed = [];

            return $this;
        }

        if (! empty($this->changed)) {
            $this->update($id, $this->changed);
        }

        $this->original = array_merge($this->original, $this->changed);
        $this->changed = [];

        return $this;
    }

    /**
     * Appelle une route Apex REST Salesforce.
     *
     * @param  string  $path  Chemin relatif sous /services/apexrest/.
     * @param  string  $method  Methode HTTP (conserve pour compatibilite ; POST utilise).
     * @param  array<string, mixed>  $params  Corps JSON de la requete.
     * @return mixed Reponse de l'API.
     */
    public function api(string $path, string $method = 'POST', array $params = []): mixed
    {
        return $this->sendApiRequest($this->apexRestUrl($path), 'POST', $params);
    }

    /**
     * Construit l'URL complete pour un endpoint de l'API REST Salesforce.
     *
     * @param  string  $path  Chemin relatif a partir de /services/data/{version}/.
     * @return string URL absolue.
     */
    protected function apiUrl(string $path): string
    {
        return $this->access['instance_url'].'/services/data/'.$this->apiCredentials['version'].'/'.$path;
    }

    /**
     * Construit l'URL complete pour un endpoint Apex REST.
     *
     * @param  string  $path  Chemin relatif a partir de /services/apexrest/.
     * @return string URL absolue.
     */
    protected function apexRestUrl(string $path): string
    {
        return $this->access['instance_url'].'/services/apexrest/'.$path;
    }

    /**
     * Envoie une requete HTTP a l'API Salesforce.
     *
     * Gere automatiquement le token Bearer et relance la requete une seule
     * fois en cas de reponse 401 (token expire).
     *
     * @param  string  $url  URL complete de l'endpoint.
     * @param  string  $method  Methode HTTP : GET, POST ou PATCH.
     * @param  array<string, mixed>|string|null  $body  Corps de la requete (tableau JSON/form ou string).
     * @param  bool  $json  Si true, envoie en JSON ; sinon en form-data.
     * @param  string|null  $sink  Chemin local pour ecrire le corps brut (telechargement).
     * @return mixed Reponse decodee de l'API, ou true si sink utilise avec succes.
     */
    protected function sendApiRequest(string $url, string $method = 'GET', array|string|null $body = null, bool $json = true, ?string $sink = null): mixed
    {
        $client = Http::withHeaders($json ? ['Content-Type' => 'application/json'] : []);

        if (! empty($this->access['access_token'])) {
            $client = $client->withToken($this->access['access_token']);
        }

        if ($sink !== null) {
            $client = $client->sink($sink);
        }

        $response = match ($method) {
            'PATCH' => $client->send('PATCH', $url, [
                'body' => is_array($body) ? (json_encode($body) ?: '{}') : ($body ?? '{}'),
            ]),
            'POST' => $json
                ? $client->post($url, is_array($body) ? $body : [])
                : $client->asForm()->post($url, is_array($body) ? $body : []),
            default => $client->get($url),
        };

        if ($response->successful()) {
            $this->retry = false;
        } elseif ($response->status() === 401 && ! $this->retry) {
            return $this->retry()->sendApiRequest($url, $method, $body, $json, $sink);
        } else {
            throw new Exception($response->body());
        }

        if ($sink !== null) {
            return true;
        }

        return $response->json();
    }

    /**
     * Compile les enregistrements API en instances de la classe appelee.
     *
     * @param  array<int, array<string, mixed>>  $records  Enregistrements a compiler.
     * @return Collection<int, static> Enregistrements compiles.
     */
    private function compileRecords(array $records): Collection
    {
        $className = static::class;

        return collect($records)->map(function (array $record) use ($className) {
            /** @var static $class */
            $class = new $className;

            // Get data from record
            foreach ($class->fillable as $fillable) {
                $getData = Arr::get($record, $fillable, null);
                $class->original[$fillable] = $getData;
            }

            return $class;
        });
    }
}
