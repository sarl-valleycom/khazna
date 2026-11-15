<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verifies Discord's Ed25519 signature over "timestamp + raw body" before
 * anything parses the request. A missing or invalid signature is a 401.
 */
class VerifyDiscordSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $publicKey = config('discord.public_key');

        if (! is_string($signature) || ! is_string($timestamp) || ! is_string($publicKey) || $publicKey === '') {
            return response()->json(['error' => 'Missing Discord signature.'], 401);
        }

        $signatureBytes = $this->decodeHex($signature);
        $publicKeyBytes = $this->decodeHex($publicKey);

        if ($signatureBytes === null || $publicKeyBytes === null || $signatureBytes === '' || $publicKeyBytes === '') {
            return response()->json(['error' => 'Invalid Discord signature.'], 401);
        }

        try {
            $valid = sodium_crypto_sign_verify_detached(
                $signatureBytes,
                $timestamp.$request->getContent(),
                $publicKeyBytes,
            );
        } catch (Throwable) {
            $valid = false;
        }

        if (! $valid) {
            return response()->json(['error' => 'Invalid Discord signature.'], 401);
        }

        return $next($request);
    }

    private function decodeHex(string $value): ?string
    {
        if (strlen($value) % 2 !== 0 || ! ctype_xdigit($value)) {
            return null;
        }

        $decoded = hex2bin($value);

        return $decoded === false ? null : $decoded;
    }
}
