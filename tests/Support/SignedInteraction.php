<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Generates an Ed25519 keypair, signs a Discord interaction exactly as Discord
 * does (signature over timestamp + raw body) and posts it to the endpoint.
 */
final class SignedInteraction
{
    public readonly string $publicKeyHex;

    private readonly string $secretKeyHex;

    public function __construct()
    {
        $keypair = sodium_crypto_sign_keypair();
        $this->publicKeyHex = bin2hex(sodium_crypto_sign_publickey($keypair));
        $this->secretKeyHex = bin2hex(sodium_crypto_sign_secretkey($keypair));
    }

    public static function make(): self
    {
        return new self;
    }

    /** Point the application at this keypair's public key. */
    public function configure(?string $guildId = '987654321098765432'): self
    {
        config()->set('discord.public_key', $this->publicKeyHex);

        if ($guildId !== null) {
            config()->set('discord.guild_id', $guildId);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function body(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{signature: string, timestamp: string}
     */
    public function signature(array $payload, string $timestamp = '1760000000'): array
    {
        $body = $timestamp.$this->body($payload);

        return [
            'signature' => bin2hex(sodium_crypto_sign_detached($body, hex2bin($this->secretKeyHex))),
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(TestCase $test, array $payload, ?string $timestamp = null): TestResponse
    {
        $timestamp ??= '1760000000';
        $signed = $this->signature($payload, $timestamp);

        return $test->call(
            'POST',
            route('discord.interactions'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE_ED25519' => $signed['signature'],
                'HTTP_X_SIGNATURE_TIMESTAMP' => $signed['timestamp'],
            ],
            $this->body($payload),
        );
    }

    /**
     * Post with deliberately broken or missing signature headers.
     *
     * @param  array<string, mixed>  $payload
     */
    public function postUnsigned(TestCase $test, array $payload, array $headers = []): TestResponse
    {
        return $test->call(
            'POST',
            route('discord.interactions'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                ...$headers,
            ],
            $this->body($payload),
        );
    }
}
