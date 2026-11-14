<?php

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function clientCsv(array $headers, array $rows, string $delimiter = ','): string
{
    $handle = fopen('php://temp', 'r+');

    if ($handle === false) {
        throw new RuntimeException('Could not open temporary CSV stream.');
    }

    fputcsv($handle, $headers, $delimiter, '"', '\\');

    foreach ($rows as $row) {
        fputcsv($handle, $row, $delimiter, '"', '\\');
    }

    rewind($handle);
    $contents = stream_get_contents($handle);
    fclose($handle);

    return $contents === false ? '' : $contents;
}

function clientCsvFile(string $contents, string $name = 'clients.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function importToken(string $location): string
{
    preg_match('/clients\/import\/([A-Za-z0-9]{40})/', $location, $matches);

    return $matches[1] ?? '';
}

test('guests are redirected from the clients pages', function () {
    $client = Client::factory()->create();

    $this->get(route('clients.index'))->assertRedirect(route('login'));
    $this->get(route('clients.show', $client))->assertRedirect(route('login'));
    $this->get(route('clients.import'))->assertRedirect(route('login'));
});

test('every role may open the client list', function (Role $role) {
    $this->actingAsRole($role);

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clients/Index'));
})->with([
    'owner' => [Role::Owner],
    'finance' => [Role::Finance],
    'account manager' => [Role::AccountManager],
]);

test('the owner can create a client and the phone is normalized', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();

    $this->post(route('clients.store'), [
        'name' => 'Acme Algeria',
        'contact' => 'Samir',
        'phone' => '0550 12 34 56',
        'email' => 'samir@acme.test',
        'address' => '1 Rue Didouche, Alger',
        'notes' => 'Pays on time.',
        'account_manager_id' => $manager->id,
        'office_id' => $office->id,
    ])->assertRedirect(route('clients.index'))->assertStatus(303);

    $client = Client::query()->where('name', 'Acme Algeria')->firstOrFail();

    expect($client->phone)->toBe('213550123456')
        ->and($client->account_manager_id)->toBe($manager->id)
        ->and($client->office_id)->toBe($office->id)
        ->and($client->contact)->toBe('Samir')
        ->and(Activity::query()->where('subject_type', Client::class)->count())->toBe(1);
});

test('an invalid phone number is refused', function () {
    $this->actingAsRole(Role::Owner);
    $manager = User::factory()->accountManager()->create();

    $this->post(route('clients.store'), [
        'name' => 'Bad phone',
        'phone' => '021 23 45 67',
        'account_manager_id' => $manager->id,
    ])->assertSessionHasErrors('phone');

    expect(Client::query()->count())->toBe(0);
});

test('an account manager creates clients for themselves and their office', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $otherManager = User::factory()->accountManager()->forOffice($otherOffice->id)->create();

    $this->post(route('clients.store'), [
        'name' => 'My client',
        'phone' => '0661 22 33 44',
        'account_manager_id' => $otherManager->id,
        'office_id' => $otherOffice->id,
    ])->assertRedirect(route('clients.index'));

    $client = Client::query()->where('name', 'My client')->firstOrFail();

    expect($client->account_manager_id)->toBe($manager->id)
        ->and($client->office_id)->toBe($office->id);
});

test('a restricted finance user cannot assign a manager from another office', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $this->actingAsRole(Role::Finance, ['office_id' => $office->id]);
    $otherManager = User::factory()->accountManager()->forOffice($otherOffice->id)->create();

    $this->post(route('clients.store'), [
        'name' => 'Wrong office',
        'phone' => '0770 11 22 33',
        'account_manager_id' => $otherManager->id,
    ])->assertSessionHasErrors('account_manager_id');
});

test('a client office must match the account manager office', function () {
    $this->actingAsRole(Role::Owner);
    $managerOffice = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($managerOffice->id)->create();

    $this->post(route('clients.store'), [
        'name' => 'Mismatch',
        'phone' => '0550 44 55 66',
        'account_manager_id' => $manager->id,
        'office_id' => $otherOffice->id,
    ])->assertSessionHasErrors('office_id');
});

test('a client can be updated and soft deleted', function () {
    $this->actingAsRole(Role::Owner);
    $client = Client::factory()->create();

    $this->patch(route('clients.update', $client), [
        'name' => 'Renamed client',
        'phone' => '0550 99 88 77',
        'account_manager_id' => $client->account_manager_id,
        'office_id' => $client->office_id,
    ])->assertRedirect(route('clients.show', $client))->assertStatus(303);

    expect($client->fresh()->name)->toBe('Renamed client')
        ->and($client->fresh()->phone)->toBe('213550998877');

    $this->delete(route('clients.destroy', $client))
        ->assertRedirect(route('clients.index'))
        ->assertStatus(303);

    $this->assertSoftDeleted($client);

    expect(fn () => $client->forceDelete())->toThrow(LogicException::class);
});

test('clients can be searched by name or phone and filtered by manager and office', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $otherManager = User::factory()->accountManager()->forOffice($office->id)->create();
    $match = Client::factory()->forManager($manager)->forOffice($office)->create([
        'name' => 'Alpha Telecom',
        'phone' => '213550111222',
    ]);
    Client::factory()->forManager($otherManager)->forOffice($office)->create([
        'name' => 'Beta Hosting',
        'phone' => '213661333444',
    ]);

    $this->get(route('clients.index', ['q' => 'alpha']))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.id', $match->id));

    $this->get(route('clients.index', ['q' => '0550 11']))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.id', $match->id));

    $this->get(route('clients.index', ['q' => '0661']))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1));

    $this->get(route('clients.index', ['account_manager' => $manager->id]))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.id', $match->id));

    $this->get(route('clients.index', ['office' => $office->id]))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 2));
});

test('account managers and office-restricted users only see their own clients', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $own = Client::factory()->forManager($manager)->forOffice($office)->create();
    Client::factory()->forOffice($office)->create();
    Client::factory()->forOffice($otherOffice)->create();

    $this->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.id', $own->id));

    $foreign = Client::factory()->forOffice($otherOffice)->create();

    $this->get(route('clients.show', $foreign))->assertForbidden();
});

test('a client page shows the profile without leaking another manager client', function () {
    $office = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    $this->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('client.id', $client->id)
            ->where('client.phoneDisplay', Phone::formatLocal($client->phone))
            ->where('client.accountManagerId', $manager->id));
});

test('the CSV template can be downloaded', function () {
    $this->actingAsRole(Role::Owner);

    $this->get(route('clients.import.template'))
        ->assertOk()
        ->assertDownload('clients-template.csv');
});

test('account managers cannot import clients', function () {
    $this->actingAsRole(Role::AccountManager);

    $this->get(route('clients.import'))->assertForbidden();
    $this->get(route('clients.import.template'))->assertForbidden();
});

test('a CSV preview separates valid, invalid and duplicate rows', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create(['email' => 'manager@khazna.test']);
    Client::factory()->create(['phone' => '213661000000']);

    $contents = clientCsv(
        ['name', 'contact', 'phone', 'email', 'address', 'notes', 'account_manager_email'],
        [
            ['Alpha', 'Ali', '0550 11 22 33', 'alpha@test.dz', 'Algiers', '', 'manager@khazna.test'],
            ['Bad phone', '', '021 00 00 00', '', '', '', 'manager@khazna.test'],
            ['No manager', '', '0770 11 22 33', '', '', '', 'missing@khazna.test'],
            ['Existing phone', '', '0661 00 00 00', '', '', '', 'manager@khazna.test'],
            ['Duplicate in file', '', '0550 11 22 33', '', '', '', 'manager@khazna.test'],
        ],
    );

    $response = $this->post(route('clients.import.preview'), [
        'file' => clientCsvFile($contents),
    ])->assertStatus(303);

    $token = importToken((string) $response->headers->get('Location'));

    $this->get(route('clients.import.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Import')
            ->where('preview.validCount', 1)
            ->where('preview.invalidCount', 2)
            ->where('preview.duplicateCount', 2)
            ->has('preview.rows', 5)
            ->where('preview.rows.0.status', 'valid')
            ->where('preview.rows.1.status', 'invalid')
            ->where('preview.rows.2.status', 'invalid')
            ->where('preview.rows.3.status', 'duplicate')
            ->where('preview.rows.4.status', 'duplicate'));
});

test('confirming an import inserts only the valid rows and consumes the preview', function () {
    $this->actingAsRole(Role::Owner);
    $manager = User::factory()->accountManager()->create(['email' => 'manager@khazna.test']);
    $contents = clientCsv(
        ['name', 'phone', 'account_manager_email'],
        [
            ['Alpha', '0550 11 22 33', 'manager@khazna.test'],
            ['Broken', 'not a phone', 'manager@khazna.test'],
        ],
    );

    $response = $this->post(route('clients.import.preview'), [
        'file' => clientCsvFile($contents),
    ])->assertStatus(303);

    $token = importToken((string) $response->headers->get('Location'));

    $this->post(route('clients.import.confirm'), ['token' => $token])
        ->assertRedirect(route('clients.index'))
        ->assertStatus(303);

    $client = Client::query()->where('name', 'Alpha')->firstOrFail();

    expect($client->phone)->toBe('213550112233')
        ->and($client->account_manager_id)->toBe($manager->id)
        ->and(Client::query()->count())->toBe(1);

    $this->post(route('clients.import.confirm'), ['token' => $token])
        ->assertRedirect(route('clients.import'))
        ->assertSessionHas('error');

    expect(Client::query()->count())->toBe(1);
});

test('CSV imports reject bad encodings and missing headers', function () {
    $this->actingAsRole(Role::Owner);

    $utf16 = "\xFF\xFE".mb_convert_encoding("name,phone,account_manager_email\nAlpha,0550 11 22 33,manager@khazna.test\n", 'UTF-16LE', 'UTF-8');

    $this->post(route('clients.import.preview'), [
        'file' => clientCsvFile($utf16),
    ])->assertSessionHasErrors('file');

    $this->post(route('clients.import.preview'), [
        'file' => clientCsvFile("name,phone\nAlpha,0550 11 22 33\n"),
    ])->assertSessionHasErrors('file');
});

test('a CSV import accepts semicolon delimiters and a UTF-8 BOM', function () {
    $this->actingAsRole(Role::Owner);
    User::factory()->accountManager()->create(['email' => 'manager@khazna.test']);

    $contents = "\xEF\xBB\xBF".clientCsv(
        ['name', 'phone', 'account_manager_email'],
        [['Alpha', '0550 11 22 33', 'manager@khazna.test']],
        ';',
    );

    $response = $this->post(route('clients.import.preview'), [
        'file' => clientCsvFile($contents),
    ])->assertStatus(303);

    $token = importToken((string) $response->headers->get('Location'));

    $this->get(route('clients.import.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('preview.validCount', 1));
});

test('a CSV import file is limited to five megabytes', function () {
    $this->actingAsRole(Role::Owner);

    $this->post(route('clients.import.preview'), [
        'file' => UploadedFile::fake()->create('clients.csv', 6000, 'text/csv'),
    ])->assertSessionHasErrors('file');
});
