<?php

namespace App\Http\Controllers\Admin;

use App\Data\SettingsPageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsUpdateRequest;
use App\Models\Setting;
use App\Support\AuditContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('viewAny', Setting::class);

        return Inertia::render('admin/Settings', [
            'page' => new SettingsPageData(
                weekStart: (string) Setting::current(Setting::WEEK_START, (string) config('khazna.week_starts_on')),
                reminderTemplate: (string) Setting::current(Setting::REMINDER_TEMPLATE, (string) config('khazna.reminder_template')),
                currencyLabel: (string) Setting::current(Setting::CURRENCY_LABEL, (string) config('khazna.currency_symbol')),
                salaryDueDay: (int) Setting::current(Setting::SALARY_DUE_DAY, (string) config('khazna.salary_due_day')),
                weekStartOptions: [
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday',
                    'monday' => 'Monday',
                ],
            ),
        ]);
    }

    public function update(SettingsUpdateRequest $request, AuditContext $audit): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $audit): void {
            $audit->withReason('Settings updated by the owner', function () use ($validated): void {
                Setting::put(Setting::WEEK_START, $validated['week_start']);
                Setting::put(Setting::REMINDER_TEMPLATE, $validated['reminder_template']);
                Setting::put(Setting::CURRENCY_LABEL, $validated['currency_label']);
                Setting::put(Setting::SALARY_DUE_DAY, (string) $validated['salary_due_day']);
            });
        });

        return to_route('settings.edit', [], 303)->with('success', __('Settings saved.'));
    }
}
