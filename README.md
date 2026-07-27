<div align="center">
    <h1>Laravel Salesforce</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/kandbaz/laravel-salesforce"><img src="https://img.shields.io/packagist/v/kandbaz/laravel-salesforce.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/kandbaz/laravel-salesforce"><img src="https://img.shields.io/packagist/php-v/kandbaz/laravel-salesforce.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/kandbaz/laravel-salesforce"><img src="https://badge.laravel.cloud/badge/kandbaz/laravel-salesforce?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/kandbaz/laravel-salesforce/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/kandbaz/laravel-salesforce/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/kandbaz/laravel-salesforce"><img src="https://img.shields.io/packagist/dt/kandbaz/laravel-salesforce.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Package CRUD pour interagir avec Salesforce via des classes sObject Eloquent-like.

## Installation

```bash
composer require supportkbz/laravel-salesforce
```

Publie la configuration :

```bash
php artisan vendor:publish --tag="laravel-salesforce-config"
```

Renseigne les variables d'environnement :

```env
SF_URL=https://your-instance.salesforce.com
SF_CONSUMER_KEY=
SF_CONSUMER_SECRET=
SF_USERNAME=
SF_PASSWORD=
SF_VERSION=v59.0
SF_OWNER_ID=

# Cache du token OAuth : `default` (Cache Laravel / CACHE_DRIVER) ou `file`
SF_CACHE_DRIVER=default
# SF_CACHE_PATH=   # mode file uniquement
# SF_CACHE_KEY=    # mode default uniquement
```

### Cache du token

| Driver | Comportement |
|---|---|
| `default` | Utilise `Cache::get/put/forget` avec le `CACHE_DRIVER` de l'app |
| `file` | Persiste un fichier chiffre via `file_put_contents` (+ Crypt) |

## Usage

### Creer une classe sObject

```bash
php artisan make:sobject Lead
# ou avec un nom d'API custom :
php artisan make:sobject Address --table=Adresse__c
```

Exemple genere / manuel :

```php
namespace App\Salesforce;

use SupportKBZ\SObject\SObject;

class Lead extends SObject
{
    protected string $table = 'Lead';

    protected array $fillable = [
        'Id',
        'Email',
        'FirstName',
        'LastName',
    ];

    protected array $map = [
        'Id' => 'id',
        'Email' => 'email',
        'FirstName' => 'first_name',
        'LastName' => 'last_name',
    ];
}
```

### Requetes

```php
$lead = (new Lead)->find('00Q...');
$lead = (new Lead)->find('email', 'john@example.com');
$lead = (new Lead)->where('Status', 'Open')->first();

$leads = (new Lead)
    ->where(function ($q) {
        $q->where('Status', 'Open')->orWhere('Status', 'Pending');
    })
    ->whereIn('LeadSource', ['Web', 'Partner'])
    ->get();
```

### Ecriture

```php
$lead = new Lead;
$lead->email = 'john@example.com';
$lead->first_name = 'John';
$lead->last_name = 'Doe';
$lead->save();

$lead->status = 'Qualified';
$lead->save();

(new Lead)->insert(['Email' => 'jane@example.com', 'LastName' => 'Doe']);
(new Lead)->update('00Q...', ['Status' => 'Closed']);
```

Les cles acceptees par `insert` / `update` / les setters peuvent etre des noms Salesforce (`Email`) ou des noms mappes (`email`).

### Upload / download

Le trait `UploadsAndDownloads` est inclus sur `SObject` :

```php
(new Lead)->upload('/path/to/file.pdf', $linkedEntityId);
(new Lead)->download($contentVersionId, storage_path('app/sf_file'));
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Salesforce! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Kandbaz](https://github.com/kandbaz)
- [All Contributors](../../contributors)

## License

Laravel Salesforce is open-sourced software licensed under the [MIT license](LICENSE.md).
