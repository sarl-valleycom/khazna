<?php

namespace App\Data;

use App\Models\Client;
use App\Models\User;
use App\Support\Phone;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClientShowData extends Data
{
    /**
     * @param  array{view: bool, update: bool, delete: bool}  $abilities
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $contact,
        public string $phone,
        public string $phoneDisplay,
        public ?string $email,
        public ?string $address,
        public ?string $notes,
        public int $accountManagerId,
        public string $accountManagerName,
        public string $accountManagerEmail,
        public ?int $officeId,
        public ?string $officeName,
        public string $createdAt,
        public array $abilities,
    ) {}

    public static function fromClient(Client $client, User $viewer): self
    {
        return new self(
            id: $client->id,
            name: $client->name,
            contact: $client->contact,
            phone: $client->phone,
            phoneDisplay: Phone::formatLocal($client->phone) ?? $client->phone,
            email: $client->email,
            address: $client->address,
            notes: $client->notes,
            accountManagerId: $client->account_manager_id,
            accountManagerName: $client->accountManager->name ?? '',
            accountManagerEmail: $client->accountManager->email ?? '',
            officeId: $client->office_id,
            officeName: $client->office?->name,
            createdAt: $client->created_at?->toIso8601String() ?? '',
            abilities: [
                'view' => $viewer->can('view', $client),
                'update' => $viewer->can('update', $client),
                'delete' => $viewer->can('delete', $client),
            ],
        );
    }
}
