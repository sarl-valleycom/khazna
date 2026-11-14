<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Metrics\DashboardMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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
            'balances' => Inertia::defer(fn (): array => $metrics->accountBalances(), 'balances'),
            // The salary card exists only for someone with the permission: for anyone else it
            // is not deferred, not built and not named in the response.
            ...($actor->can('view-salaries') ? [
                'salary' => Inertia::defer(fn (): array => ['billCents' => $metrics->salaryBill()], 'salary'),
            ] : []),
        ]);
    }
}
