<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use SupportKBZ\SObject\SObject;

beforeEach(function () {
    $this->baseUrl = config('salesforce.url');
    $this->version = config('salesforce.version');

    mockSalesforceAuthentication($this->baseUrl);

    // Clean auth cache
    (new SObject)->forgetTokenCache();
});

/**
 * Mock l'endpoint d'authentification Salesforce.
 */
function mockSalesforceAuthentication(string $baseUrl): void
{
    Http::fake([
        "{$baseUrl}/services/oauth2/token" => Http::response([
            'access_token' => 'fake-access-token',
            'instance_url' => $baseUrl,
            'id' => 'https://login.salesforce.com/id/ORG/USER',
            'issued_at' => (string) now()->getTimestampMs(),
            'signature' => base64_encode('fake-signature'),
            'expiration' => now()->addHour()->getTimestamp(),
        ], 200),
    ]);
}

test('find return one record', function () {
    Http::fake([
        "{$this->baseUrl}/services/oauth2/token" => Http::response([
            'access_token' => 'fake-access-token',
            'instance_url' => $this->baseUrl,
            'id' => 'https://login.salesforce.com/id/ORG/USER',
            'issued_at' => (string) now()->getTimestampMs(),
            'signature' => base64_encode('fake-signature'),
            'expiration' => now()->addHour()->getTimestamp(),
        ], 200),
        "{$this->baseUrl}/services/data/{$this->version}/query/?q=*" => Http::sequence()
            ->push('Unauthorized', 401)
            ->push(['records' => [
                ['Id' => 'TEST', 'Name' => 'John Doe'],
            ]], 200),
    ]);

    $leadHelper = new class extends SObject
    {
        protected string $table = 'Lead';

        protected array $fillable = ['Id', 'Name'];
    };

    // Check authentification
    $path = $leadHelper->getCachePath();
    $authentification = json_decode(Crypt::decryptString(file_get_contents($path)), true);
    expect($authentification['access_token'])->toBe('fake-access-token');
    expect($authentification['id'])->toBe('https://login.salesforce.com/id/ORG/USER');
    expect($authentification['signature'])->toBe(base64_encode('fake-signature'));

    // Check find
    $lead = $leadHelper->find('TEST');
    expect($lead)->not->toBeNull()
        ->and($lead->Id)->toBe('TEST')
        ->and($lead->Name)->toBe('John Doe');
});

test('get return all records', function () {
    Http::fake([
        "{$this->baseUrl}/services/oauth2/token" => Http::response([
            'access_token' => 'fake-access-token',
            'instance_url' => $this->baseUrl,
            'id' => 'https://login.salesforce.com/id/ORG/USER',
            'issued_at' => (string) now()->getTimestampMs(),
            'signature' => base64_encode('fake-signature'),
            'expiration' => now()->addHour()->getTimestamp(),
        ], 200),
        "{$this->baseUrl}/services/data/{$this->version}/query/?q=*" => Http::response([
            'records' => [
                ['Id' => 'TEST', 'Name' => 'John Doe', 'AccountId' => 'ACCOUNT_TEST'],
                ['Id' => 'TEST2', 'Name' => 'Jane Doe', 'AccountId' => 'ACCOUNT_TEST'],
            ],
        ], 200),
        "{$this->baseUrl}/services/data/{$this->version}/sobjects/Lead/TEST2" => Http::response(null, 204),
        "{$this->baseUrl}/services/data/{$this->version}/sobjects/Lead" => Http::response([
            'id' => 'TEST3',
            'errors' => [],
        ], 200),
    ]);

    $leadHelper = new class extends SObject
    {
        protected string $table = 'Lead';

        protected array $fillable = ['Id', 'Name', 'AccountId'];

        protected array $map = [
            'Id' => 'id',
            'Name' => 'name',
            'AccountId' => 'account_id',
        ];

        /**
         * Expose la clause WHERE compilee pour les assertions.
         */
        public function getWhereClause(): string
        {
            return $this->buildWhereClause();
        }
    };

    $leadHelper = $leadHelper->where(function ($query) {
        $query->where('AccountId', '=', 'ACCOUNT_TEST')->orWhere('AccountId', '=', 'ACCOUNT_TEST2');
    })->where('AccountId', '=', 'ACCOUNT_TEST2')->orWhere('AccountId', '=', 'ACCOUNT_TEST3');

    // Check where clause
    expect($leadHelper->getWhereClause())->toBe('(AccountId = %27ACCOUNT_TEST%27 OR AccountId = %27ACCOUNT_TEST2%27) AND AccountId = %27ACCOUNT_TEST2%27 OR AccountId = %27ACCOUNT_TEST3%27');

    $leads = $leadHelper->get();

    expect($leads)->toHaveCount(2);
    expect($leads[0]->Id)->toBe('TEST')
        ->and($leads[0]->Name)->toBe('John Doe')
        ->and($leads[1]->Id)->toBe('TEST2')
        ->and($leads[1]->Name)->toBe('Jane Doe');

    $firstLead = $leadHelper->first();
    expect($firstLead)->not->toBeNull()
        ->and($firstLead->id)->toBe('TEST')
        ->and($firstLead->Name)->toBe('John Doe');

    $lastLead = $leadHelper->last();
    expect($lastLead)->not->toBeNull()
        ->and($lastLead->Id)->toBe('TEST2')
        ->and($lastLead->name)->toBe('Jane Doe');

    // Ignore save if no changes
    $lastLead->save();

    // Remove changed if rewrite to original value
    $lastLead->name2 = 'Toto';
    $lastLead->name = 'Jane Doe';

    // Test set value
    $lastLead->name = 'John';
    expect($lastLead->name)->toBe('John');

    // Test save update
    $lastLead->save();

    expect($lastLead->toArray())->toBe([
        'Id' => 'TEST2',
        'Name' => 'John',
        'AccountId' => 'ACCOUNT_TEST',
    ]);

    // Test save insert
    $newLead = new $leadHelper;
    $newLead->name = 'John Doe';
    $newLead->account_id = 'ACCOUNT_TEST3';
    $newLead->save();

    expect($newLead->Id)->toBe('TEST3');
    expect($newLead->toArray())->toBe([
        'Id' => 'TEST3',
        'Name' => 'John Doe',
        'AccountId' => 'ACCOUNT_TEST3',
    ]);

    // Test pluck
    expect($leads->pluck('Id')->toArray())->toBe([
        'TEST',
        'TEST2',
    ]);
});
