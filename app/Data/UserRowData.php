<?php

namespace App\Data;

use App\Enums\Role;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public Role $role,
        public string $roleLabel,
        public ?int $officeId,
        public ?string $officeName,
        public bool $active,
        public bool $isSelf,
    ) {}

    public static function fromUser(User $user, ?User $viewer = null): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            roleLabel: $user->role->label(),
            officeId: $user->office_id,
            officeName: $user->office?->name,
            active: $user->active,
            isSelf: $viewer?->id === $user->id,
        );
    }
}
