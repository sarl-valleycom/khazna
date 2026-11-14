<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\Metrics\DashboardMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * @return array{checkedAt: string|null, problems: array<int, string>}|null
     */
    private function ledgerCheck(): ?array
    {
        $raw = Setting::current(Setting::LEDGER_CHECK);
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data)
            ? ['checkedAt' => $data['checked_at'] ?? null, 'problems' => array_values($data['problems'] ?? [])]
            : null;
    }

    public function __invoke(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        // A restricted user is always held to their office, whatever the URL says.
        $officeId = $actor->office_id ?? ($request->integer('office') ?: null);
        $metrics = new DashboardMetrics($actor, $officeId);

        return Inertia::render('dashboard/Index', [
            'officeId' => $officeId,
            'monthLabel' => $metrics->period->anchor->format('F Y'),
            // Each group loads on its own after the page has appeared, so one slow card
            // never holds up the others.
            'kpis' => Inertia::defer(fn (): array => [
                'collectedCents' => $metrics->collectedThisMonth(),
                'outstandingCents' => $metrics->outstanding(),
                'overdueCents' => $metrics->overdue(),
                'mrrCents' => $metrics->mrr(),
                'renewals' => $metrics->upcomingRenewals(),
            ], 'kpis'),
            // The latest ledger:verify result, for the owner only.
            'ledgerCheck' => $actor->isOwner() ? $this->ledgerCheck() : null,
            'balances' => Inertia::defer(fn (): array => $metrics->accountBalances(), 'balances'),
            // The salary card exists only for someone with the permission: for anyone else it
            // is not deferred, not built and not named in the response.
            ...($actor->can('view-salaries') ? [
                'salary' => Inertia::defer(fn (): array => ['billCents' => $metrics->salaryBill()], 'salary'),
            ] : []),
        ]);
    }
}
