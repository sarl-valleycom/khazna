<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    /**
     * @param  array<string, bool>  $abilities
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $role,
        public ?int $officeId,
        public bool $active,
        public ?string $avatar = null,
        public ?string $emailVerifiedAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public bool $twoFactorEnabled = false,
        public array $abilities = [],
    ) {}

    public static function fromUser(?User $user): ?self
    {
        if ($user === null) {
            return null;
        }

        $attributes = $user->getAttributes();

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $attributes['role'] ?? null,
            officeId: $attributes['office_id'] ?? null,
            active: (bool) ($attributes['active'] ?? true),
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            createdAt: $user->created_at?->toIso8601String(),
            updatedAt: $user->updated_at?->toIso8601String(),
            twoFactorEnabled: ($attributes['two_factor_confirmed_at'] ?? null) !== null,
        );
    }
}
