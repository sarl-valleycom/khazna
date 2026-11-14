<?php

namespace App\Data;

use App\Models\Client;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContractClientOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $officeId,
        public ?string $officeName,
    ) {}

    public static function fromClient(Client $client): self
    {
        return new self(
            id: $client->id,
            name: $client->name,
            officeId: $client->office_id,
            officeName: $client->office?->name,
        );
    }
}
