<?php

namespace App\Models\Concerns;

use App\Support\AuditContext;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept($this->auditExcludedAttributes())
            ->useLogName('audit');
    }

    /**
     * Sensitive fields never reach the audit trail. Salary fields are not
     * excluded: the audit screen is owner-only, not the log itself.
     *
     * @return list<string>
     */
    protected function auditExcludedAttributes(): array
    {
        return [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];
    }

    public function beforeActivityLogged(Activity $activity, string $event): void
    {
        $reason = app(AuditContext::class)->reason();

        if ($reason === null) {
            return;
        }

        $activity->properties = ($activity->properties ?? collect())->put('reason', $reason);
    }
}
