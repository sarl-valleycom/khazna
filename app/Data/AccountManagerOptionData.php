<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AccountManagerOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $officeId,
        public ?string $officeName,
        public bool $active,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            officeId: $user->office_id,
            officeName: $user->office?->name,
            active: $user->active,
        );
    }
}
