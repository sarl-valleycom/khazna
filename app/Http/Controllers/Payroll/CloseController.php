<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\CloseMonth;
use App\Data\PayrollLineData;
use App\Http\Controllers\Controller;
use App\Support\AlgerianDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Everything here sits behind the `view-salaries` gate (see routes/web.php). */
class CloseController extends Controller
{
    public function show(Request $request, CloseMonth $close): Response
    {
        $month = $this->month($request->string('month')->toString());
        $lines = $close->preview($month, $this->actor($request));

        $ready = array_filter($lines, fn (PayrollLineData $line): bool => $line->state === 'ready');
        $total = array_sum(array_map(fn (PayrollLineData $line): int => $line->netCents, $ready));

        return Inertia::render('payroll/Close', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'lines' => array_values($lines),
            'readyCount' => count($ready),
            'totalCents' => $total,
            'totalFormatted' => Money::fromCents($total)->format(),
            'result' => $request->session()->get('close_result'),
        ]);
    }

    public function store(Request $request, CloseMonth $close): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = CarbonImmutable::parse($validated['month'].'-01');
        $result = $close->handle($month, $this->actor($request));

        return to_route('payroll.close', ['month' => $validated['month']], 303)
            ->with('close_result', [
                'created' => count($result['created']),
                'existing' => count($result['existing']),
                'skipped' => count($result['skipped']),
            ])
            ->with('success', $result['created'] === []
                ? __('Nothing new to close: every employee already had a salary due for this month.')
                : __(':count salary dues created.', ['count' => count($result['created'])]));
    }

    private function month(string $value): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1
            ? CarbonImmutable::parse($value.'-01')
            : AlgerianDate::today()->startOfMonth()->subMonth();
    }
}
