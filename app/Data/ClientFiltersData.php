<?php

namespace App\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClientFiltersData extends Data
{
    public function __construct(
        public string $q = '',
        public ?int $accountManager = null,
        public ?int $office = null,
        public string $sort = 'name',
        public string $direction = 'asc',
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString();
        $perPage = $request->integer('per_page', 10);

        return new self(
            q: trim($request->string('q')->toString()),
            accountManager: $request->integer('account_manager') ?: null,
            office: $request->integer('office') ?: null,
            sort: in_array($sort, ['name', 'phone', 'email', 'created_at'], true) ? $sort : 'name',
            direction: $direction === 'desc' ? 'desc' : 'asc',
            perPage: in_array($perPage, [10, 25, 50], true) ? $perPage : 10,
        );
    }
}
