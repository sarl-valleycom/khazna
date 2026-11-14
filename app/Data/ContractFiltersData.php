<?php

namespace App\Data;

use App\Enums\ContractStatus;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContractFiltersData extends Data
{
    public function __construct(
        public string $q = '',
        public string $status = '',
        public ?int $client = null,
        public ?int $office = null,
        public string $sort = 'starts_on',
        public string $direction = 'desc',
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString();
        $perPage = $request->integer('per_page', 10);

        return new self(
            q: trim($request->string('q')->toString()),
            status: in_array($status, ContractStatus::values(), true) ? $status : '',
            client: $request->integer('client') ?: null,
            office: $request->integer('office') ?: null,
            sort: in_array($sort, ['starts_on', 'amount_cents', 'status', 'created_at'], true) ? $sort : 'starts_on',
            direction: $direction === 'asc' ? 'asc' : 'desc',
            perPage: in_array($perPage, [10, 25, 50], true) ? $perPage : 10,
        );
    }
}
