<?php

namespace App\Actions\Attendance;

use App\Models\Holiday;
use Illuminate\Support\Facades\DB;

/**
 * A holiday is one date with a label. A date that was removed earlier is
 * brought back instead of failing on the unique date column.
 */
class AddHoliday
{
    public function handle(string $date, string $label): Holiday
    {
        return DB::transaction(function () use ($date, $label): Holiday {
            $existing = Holiday::withTrashed()->where('date', $date)->lockForUpdate()->first();

            if ($existing === null) {
                return Holiday::query()->create(['date' => $date, 'label' => $label]);
            }

            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update(['label' => $label]);

            return $existing;
        });
    }
}
