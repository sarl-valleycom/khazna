<?php

namespace App\Models\Concerns;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One visibility rule for every model: an office-restricted user sees only
 * that office, and an account manager sees only rows they own.
 *
 * Models declare which columns carry the office and the owner. A model without
 * an owner column is only filtered by office; a model without an office column
 * is only filtered by account manager.
 *
 * @mixin Model
 */
trait HasVisibility
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        /** @var static $model */
        $model = $query->getModel();

        $officeColumn = $model->visibilityOfficeColumn();

        if ($user->office_id !== null && $officeColumn !== null) {
            $query->where($model->qualifyColumn($officeColumn), $user->office_id);
        }

        $ownerColumn = $model->visibilityOwnerColumn();

        if ($user->role === Role::AccountManager && $ownerColumn !== null) {
            $query->where($model->qualifyColumn($ownerColumn), $user->id);
        }
    }

    public function visibilityOfficeColumn(): ?string
    {
        return 'office_id';
    }

    public function visibilityOwnerColumn(): ?string
    {
        return null;
    }
}
