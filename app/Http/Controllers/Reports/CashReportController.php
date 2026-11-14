<?php

namespace App\Http\Controllers\Reports;

use App\Data\PaymentAccountData;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Office;
use App\Models\User;
use App\Queries\CashReportQuery;
use App\Support\AlgerianDate;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashReportController extends Controller
{
    public function __invoke(Request $request, CashReportQuery $report): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $today = AlgerianDate::today();
        $to = $this->date($this->queryString($request, 'to'), $today);
        $from = $this->date($this->queryString($request, 'from'), $today->startOfYear());

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // A restricted user is held to their own office whatever the URL says.
        $officeId = $actor->office_id ?? ($request->integer('office') ?: null);
        $accountId = $request->integer('account') ?: null;

        return Inertia::render('reports/Cash', [
            ...$report->handle($actor, $from, $to, $officeId, $accountId),
            'filters' => ['office' => $officeId, 'account' => $accountId],
            'accounts' => Account::query()->visibleTo($actor)->with('office:id,name')->orderBy('office_id')->orderBy('name')->get()
                ->map(fn (Account $account): PaymentAccountData => PaymentAccountData::fromAccount($account))->values()->all(),
            'officeOptions' => $actor->office_id === null
                ? Office::query()->orderBy('name')->get(['id', 'name'])->map(fn (Office $office): array => ['id' => $office->id, 'name' => $office->name])->values()->all()
                : [],
        ]);
    }

    private function date(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        return Dates::day($value) ?? $fallback;
    }
}
