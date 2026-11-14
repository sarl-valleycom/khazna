<?php

namespace App\Data;

use App\Enums\Role;
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
        public ?Role $role,
        public ?int $officeId,
        public bool $active,
        public ?string $avatar = null,
        public ?string $emailVerifiedAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public bool $twoFactorEnabled = false,
        public array $abilities = [],
    ) {}

    /**
     * @param  array<string, bool>  $abilities
     */
    public static function fromUser(?User $user, array $abilities = []): ?self
    {
        if ($user === null) {
            return null;
        }

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            officeId: $user->office_id,
            active: $user->active,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            createdAt: $user->created_at?->toIso8601String(),
            updatedAt: $user->updated_at?->toIso8601String(),
            twoFactorEnabled: $user->two_factor_confirmed_at !== null,
            abilities: $abilities,
        );
    }
}
