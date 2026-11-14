<?php

namespace App\Models\Concerns;

use App\Support\AuditContext;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait Auditable
{
    use LogsActivity;

    /**
     * Attributes whose values never reach the log but whose change must still
     * leave a row (who and when), e.g. a password reset or 2FA being switched.
     * remember_token is left out: it rotates on every sign-in and sign-out.
     *
     * @return list<string>
     */
    protected function auditSecurityAttributes(): array
    {
        return ['password', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    public static function bootAuditable(): void
    {
        static::updated(function (self $model): void {
            $changed = array_values(array_filter(
                $model->auditSecurityAttributes(),
                fn (string $attribute): bool => $model->wasChanged($attribute),
            ));

            if ($changed === []) {
                return;
            }

            activity('audit')
                ->performedOn($model)
                ->event('security_changed')
                ->withProperties(['fields' => $changed])
                ->log('security_changed');
        });
    }

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
