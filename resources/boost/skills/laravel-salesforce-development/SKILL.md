---
name: laravel-salesforce-development
description: >
  Configure and apply the Laravel Salesforce package in Laravel applications.
  Use when creating SObject classes, querying Salesforce, inserting/updating
  records, uploading ContentVersion files, or configuring SF_* credentials.
license: MIT
metadata:
  author: Kandbaz
---

# Laravel Salesforce

Use this skill when a Laravel application needs to integrate `kandbaz/laravel-salesforce`.

## Primary Goal

- apply the package's public API in the smallest correct way

## Workflow

### 1. Inspect the Laravel app context

- confirm the app is a Laravel project
- check whether `config/salesforce.php` is published and `SF_*` env vars are set

### 2. Configure credentials

```bash
php artisan vendor:publish --tag="laravel-salesforce-config"
```

Required config keys: `salesforce.url`, `consumer_key`, `consumer_secret`, `username`, `password`, `version`.

Token cache (`salesforce.cache.driver`):

- `default` — Laravel `Cache` facade (`CACHE_DRIVER`)
- `file` — encrypted file via `file_put_contents` (`salesforce.cache.path`)

### 3. Create an SObject class

```bash
php artisan make:sobject Lead
php artisan make:sobject Address --table=Adresse__c
```

Each class must extend `SupportKBZ\SObject\SObject` and define:

- `protected string $table` — API name of the sObject
- `protected array $fillable` — fields selected/written
- `protected array $map` — optional Salesforce field → local alias (`Email` => `email`)

Place classes under `App\Salesforce` by default.

### 4. Query and mutate

```php
(new Lead)->find('00Q...');
(new Lead)->where('Status', 'Open')->get();
(new Lead)->where(fn ($q) => $q->where('Status', 'Open')->orWhere('Status', 'Pending'))->first();

$lead = new Lead;
$lead->email = 'john@example.com';
$lead->save();
```

Use mapped aliases or Salesforce API names interchangeably for getters/setters and `insert`/`update` keys.

### 5. Files

```php
(new Lead)->upload($path, $linkedEntityId);
(new Lead)->download($contentVersionId, $outputPathWithoutExtension);
```

Requires `salesforce.owner_id` for uploads.

## Examples

- New Lead object: `make:sobject Lead`, fill `$fillable`/`$map`, then `(new Lead)->where('Email', $email)->first()`
- Nested SOQL: `where(function ($q) { $q->where(...)->orWhere(...); })`

## Anti-patterns

- do not put project-specific sObjects (Lead business fields, custom __c picklists) inside the package — keep them in the app
- do not call `env()` outside config files; read `config('salesforce.*')`
- do not document package internals as public API
