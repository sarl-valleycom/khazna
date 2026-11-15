<?php

use App\Discord\InteractionResponse;
use App\Http\Middleware\VerifyDiscordSignature;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\SignedInteraction;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function commandInteraction(string $name, array $overrides = []): array
{
    return [
        'id' => '111111111111111111',
        'application_id' => '123456789012345678',
        'type' => 2,
        'guild_id' => '987654321098765432',
        'channel_id' => '555555555555555555',
        'member' => [
            'user' => ['id' => '444444444444444444', 'username' => 'karim'],
        ],
        'data' => [
            'id' => '999999999999999999',
            'name' => $name,
            'type' => 1,
            'options' => [],
        ],
        ...$overrides,
    ];
}

test('the interactions route is outside the web session and CSRF stack', function () {
    $route = Route::getRoutes()->getByName('discord.interactions');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain(VerifyDiscordSignature::class)
        ->and($middleware)->not->toContain('web')
        ->and($middleware)->not->toContain('csrf');
});

test('a signed ping is answered with a pong', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->post($this, ['type' => 1])
        ->assertOk()
        ->assertExactJson(['type' => InteractionResponse::TYPE_PONG]);
});

test('a missing or invalid signature is rejected before the body is used', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->postUnsigned($this, ['type' => 1])->assertStatus(401);
    $signer->postUnsigned($this, ['type' => 1], [
        'HTTP_X_SIGNATURE_ED25519' => str_repeat('a', 128),
        'HTTP_X_SIGNATURE_TIMESTAMP' => '1760000000',
    ])->assertStatus(401);

    // Not even valid JSON: an invalid signature must not lead to a parser error.
    $this->call('POST', route('discord.interactions'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => 'not-hex',
        'HTTP_X_SIGNATURE_TIMESTAMP' => '1760000000',
    ], '{broken')->assertStatus(401);
});

test('a signature made for a different body is rejected', function () {
    $signer = SignedInteraction::make()->configure();
    $signed = $signer->signature(['type' => 1]);

    $this->call('POST', route('discord.interactions'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => $signed['signature'],
        'HTTP_X_SIGNATURE_TIMESTAMP' => $signed['timestamp'],
    ], json_encode(['type' => 2], JSON_THROW_ON_ERROR))->assertStatus(401);
});

test('commands from another server are rejected', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->post($this, commandInteraction('check-in', ['guild_id' => '111111111111111111']))
        ->assertStatus(403);
});

test('a command sent in a DM with the bot is routed and an unlinked user is told so', function () {
    $signer = SignedInteraction::make()->configure();

    $payload = commandInteraction('check-in');
    unset($payload['guild_id'], $payload['member']);
    $payload['user'] = ['id' => '444444444444444444', 'username' => 'karim'];

    $signer->post($this, $payload)
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL);
});

test('a component or modal sent from a DM is routed without a guild id', function () {
    $signer = SignedInteraction::make()->configure();

    foreach ([3, 5] as $type) {
        $signer->post($this, [
            'id' => '111111111111111111',
            'application_id' => '123456789012345678',
            'type' => $type,
            'user' => ['id' => '444444444444444444', 'username' => 'karim'],
            'data' => ['custom_id' => 'nothing:registered:1'],
        ])
            ->assertOk()
            ->assertJsonPath('data.content', __('discord.errors.unknown_interaction'));
    }
});

test('a component from another guild is still rejected', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->post($this, [
        'id' => '111111111111111111',
        'type' => 3,
        'guild_id' => '111111111111111111',
        'member' => ['user' => ['id' => '444444444444444444']],
        'data' => ['custom_id' => 'nothing:registered:1'],
    ])->assertStatus(403);
});

test('a Discord ID above 2^53 is compared as an exact string', function () {
    $snowflake = '9007199254740993';
    $signer = SignedInteraction::make()->configure($snowflake);

    $signer->post($this, commandInteraction('check-in', ['guild_id' => $snowflake]))
        ->assertOk()
        ->assertJsonPath('type', InteractionResponse::TYPE_MESSAGE);

    // The neighbouring fake ID is not the configured guild.
    $signer->post($this, commandInteraction('check-in', ['guild_id' => '9007199254740994']))
        ->assertStatus(403);
});

test('an unknown command gets a private explanation, not an exception', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->post($this, commandInteraction('leave'))
        ->assertOk()
        ->assertJsonPath('type', InteractionResponse::TYPE_MESSAGE)
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', __('discord.errors.unknown_interaction'));
});

test('a linked employee who sends /check-in in a DM gets a saved session', function () {
    $signer = SignedInteraction::make()->configure();
    $office = Office::factory()->create();
    $employee = Employee::factory()->forOffice($office)->linkedTo('444444444444444444')->create();

    $payload = commandInteraction('check-in');
    unset($payload['guild_id'], $payload['member']);
    $payload['user'] = ['id' => '444444444444444444', 'username' => 'karim'];

    $signer->post($this, $payload)->assertOk();

    expect(AttendanceSession::query()->where('employee_id', $employee->id)->open()->count())->toBe(1);
});
