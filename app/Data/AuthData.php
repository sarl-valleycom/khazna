<?php

namespace App\Data;

use App\Enums\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AuthData extends Data
{
    public function __construct(
        public ?UserData $user,
        public ?Role $role,
    ) {}
}
