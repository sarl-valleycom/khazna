<?php

namespace App\Data;

use App\Models\Activity;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AuditLogRowData extends Data
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int $id,
        public string $event,
        public string $description,
        public ?string $subjectType,
        public ?int $subjectId,
        public ?string $causerName,
        public ?int $causerId,
        public string $createdAt,
        public array $changes,
        public ?string $reason,
    ) {}

    public static function fromActivity(Activity $activity): self
    {
        $changes = $activity->attribute_changes;

        return new self(
            id: $activity->id,
            event: $activity->event ?? $activity->description,
            description: $activity->description,
            subjectType: $activity->subject_type,
            subjectId: $activity->subject_id,
            causerName: $activity->causer instanceof User ? $activity->causer->name : null,
            causerId: $activity->causer_id,
            createdAt: $activity->created_at?->toIso8601String() ?? '',
            changes: $changes?->toArray() ?? [],
            reason: $activity->getProperty('reason'),
        );
    }
}
