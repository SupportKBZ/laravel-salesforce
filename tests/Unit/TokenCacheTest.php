<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use SupportKBZ\SObject\Cache\TokenCacheFactory;
use SupportKBZ\SObject\SObject;

beforeEach(function () {
    $this->baseUrl = config('salesforce.url');

    Http::fake([
        "{$this->baseUrl}/services/oauth2/token" => Http::response([
            'access_token' => 'fake-access-token',
            'instance_url' => $this->baseUrl,
            'id' => 'https://login.salesforce.com/id/ORG/USER',
            'issued_at' => (string) now()->getTimestampMs(),
            'signature' => base64_encode('fake-signature'),
            'expiration' => now()->addHour()->getTimestamp(),
        ], 200),
    ]);
});

test('file cache driver persist le token chiffre sur disque', function () {
    config()->set('salesforce.cache.driver', 'file');
    config()->set('salesforce.cache.path', storage_path('framework/cache'));

    Cache::flush();
    (new SObject)->forgetTokenCache();

    $object = new SObject;
    $path = $object->getCachePath();

    expect(file_exists($path))->toBeTrue();

    $cached = json_decode(Crypt::decryptString((string) file_get_contents($path)), true);
    expect($cached['access_token'])->toBe('fake-access-token');
});

test('default cache driver utilise le Cache Laravel', function () {
    config()->set('salesforce.cache.driver', 'default');
    config()->set('salesforce.cache.key', 'salesforce.oauth_token.testing');

    Cache::flush();

    $object = new SObject;

    expect(fn () => $object->getCachePath())
        ->toThrow(Exception::class);

    $cached = Cache::get('salesforce.oauth_token.testing');
    expect($cached)->toBeArray()
        ->and($cached['access_token'])->toBe('fake-access-token');

    $object->forgetTokenCache();
    expect(Cache::get('salesforce.oauth_token.testing'))->toBeNull();
});

test('token cache factory refuse un driver invalide', function () {
    config()->set('salesforce.cache.driver', 'redis');

    expect(fn () => TokenCacheFactory::make())
        ->toThrow(InvalidArgumentException::class);
});
