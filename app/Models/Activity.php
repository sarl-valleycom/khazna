<?php

namespace App\Models;

use LogicException;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * The audit trail is append-only: application code may create rows but never
 * update or delete them. Retention pruning is an infrastructure concern.
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('The audit log is append-only; activities cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('The audit log is append-only; activities cannot be deleted.');
        });
    }
}
