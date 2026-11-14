<?php

namespace App\Data;

use App\Models\Office;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OfficeRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $city,
        public bool $active,
        public int $usersCount,
    ) {}

    public static function fromOffice(Office $office): self
    {
        return new self(
            id: $office->id,
            name: $office->name,
            city: $office->city,
            active: $office->active,
            usersCount: (int) ($office->users_count ?? 0),
        );
    }
}
