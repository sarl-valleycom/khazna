<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\CloseMonth;
use App\Data\PaymentAccountData;
use App\Data\PayrollLineData;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Everything here sits behind the `view-salaries` gate (see routes/web.php). */
class CloseController extends Controller
{
    public function show(Request $request, CloseMonth $close): Response
    {
        $month = $this->month($this->queryString($request, 'month'));
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
            'accounts' => $this->accounts($this->actor($request)),
            'methods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])
                ->all(),
        ]);
    }

    public function store(Request $request, CloseMonth $close): RedirectResponse
    {
        $validated = $request->validate([
            // Never a month that has not happened yet: that would put a year of salary dues
            // in the forecast and block closing the real month later.
            'month' => ['required', 'date_format:Y-m', fn (string $attribute, mixed $value, Closure $fail) => (string) $value > AlgerianDate::today()->format('Y-m')
                ? $fail(__('You cannot close a month that has not started yet.'))
                : null],
            // Amounts the owner typed over the worked-out net, keyed by employee id.
            'amounts' => ['sometimes', 'array'],
            'amounts.*' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            // Optional: also pay each new salary due, each from its own account.
            'pay' => ['sometimes', 'nullable', 'array'],
            // The account that pays each employee, keyed by employee id.
            'pay.accounts' => ['required_with:pay', 'array'],
            'pay.accounts.*' => ['nullable', 'integer'],
            'pay.method' => ['required_with:pay', Rule::enum(PaymentMethod::class)],
            'pay.paid_on' => ['required_with:pay', 'date_format:Y-m-d'],
        ]);

        $month = CarbonImmutable::parse($validated['month'].'-01');
        $result = $close->handle($month, $this->actor($request), $validated['amounts'] ?? [], $validated['pay'] ?? null);

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

    /** @return array<int, PaymentAccountData> */
    private function accounts(User $actor): array
    {
        return Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->with('office:id,name')
            ->orderBy('office_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): PaymentAccountData => PaymentAccountData::fromAccount($account))
            ->values()
            ->all();
    }
}
