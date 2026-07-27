<?php

declare(strict_types=1);

it('merges the salesforce config', function () {
    expect(config('salesforce.url'))->toBe('https://test.salesforce.com')
        ->and(config('salesforce.version'))->toBe('v59.0')
        ->and(config('salesforce.consumer_key'))->toBe('test-consumer-key')
        ->and(config('salesforce.cache.driver'))->toBe('file');
});

it('registers the make:sobject command', function () {
    $this->artisan('make:sobject', ['--help' => true])
        ->assertSuccessful();
});
