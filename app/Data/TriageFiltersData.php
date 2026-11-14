<?php

namespace App\Data;

use App\Enums\Direction;
use App\Enums\DueKind;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TriageFiltersData extends Data
{
    public const TABS = ['overdue', 'today', 'week', 'month', 'attention'];

    public function __construct(
        public string $tab = 'overdue',
        public string $direction = '',
        public string $kind = '',
        public ?int $client = null,
        public ?int $office = null,
        public ?int $assignee = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $tab = $request->string('tab')->toString();
        $direction = $request->string('direction')->toString();
        $kind = $request->string('kind')->toString();

        return new self(
            tab: in_array($tab, self::TABS, true) ? $tab : 'overdue',
            direction: in_array($direction, Direction::values(), true) ? $direction : '',
            kind: in_array($kind, DueKind::values(), true) ? $kind : '',
            client: $request->integer('client') ?: null,
            office: $request->integer('office') ?: null,
            assignee: $request->integer('assignee') ?: null,
        );
    }
}
