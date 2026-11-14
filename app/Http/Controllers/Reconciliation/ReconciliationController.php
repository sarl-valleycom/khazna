<?php

namespace App\Http\Controllers\Reconciliation;

use App\Actions\Ledger\SaveReconciliation;
use App\Data\PaymentAccountData;
use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ReconciliationRequest;
use App\Http\Requests\Ledger\ReversalRequest;
use App\Models\Account;
use App\Models\Reconciliation;
use App\Models\User;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    /** Days after which an account is due for another count. Cash moves faster, so it is weekly. */
    public const GENERAL_DAYS = 30;

    public const CASH_DAYS = 7;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Reconciliation::class);

        $actor = $this->actor($request);
        $today = AlgerianDate::today();

        $accounts = Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->with('office:id,name')
            ->orderBy('office_id')
            ->orderBy('name')
            ->get();

        $last = Reconciliation::query()
            ->whereIn('account_id', $accounts->modelKeys())
            ->selectRaw('account_id, MAX(as_of) AS last_as_of')
            ->groupBy('account_id')
            ->pluck('last_as_of', 'account_id');

        $selected = $accounts->firstWhere('id', $request->integer('account')) ?? $accounts->first();
        $asOf = $this->date($request->string('as_of')->toString(), $today);

        return Inertia::render('accounts/Reconcile', [
            'accounts' => $accounts->map(function (Account $account) use ($last, $today): array {
                $date = $last->get($account->id);
                $limit = $account->type === AccountType::Cash ? self::CASH_DAYS : self::GENERAL_DAYS;
                $days = $date === null ? null : (int) CarbonImmutable::parse((string) $date)->diffInDays($today);

                return [
                    ...PaymentAccountData::fromAccount($account)->toArray(),
                    'lastReconciled' => $date === null ? null : CarbonImmutable::parse((string) $date)->toDateString(),
                    'due' => $days === null || $days > $limit,
                    'everyDays' => $limit,
                ];
            })->values()->all(),
            'selectedId' => $selected?->id,
            'asOf' => $asOf->toDateString(),
            'systemBalanceCents' => $selected !== null && $asOf->gte($selected->opening_date) ? $selected->balanceOn($asOf) : null,
            'openingDate' => $selected?->opening_date->toDateString(),
            'history' => $selected === null ? [] : $selected->reconciliations()
                ->with('creator:id,name')
                ->orderByDesc('as_of')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (Reconciliation $row): array => [
                    'id' => $row->id,
                    'asOf' => $row->as_of->toDateString(),
                    'countedCents' => $row->counted_balance_cents,
                    'systemCents' => $row->system_balance_cents,
                    'differenceCents' => $row->difference_cents,
                    'note' => $row->note,
                    'recorded' => $row->adjustment_entry_id !== null,
                    'open' => $row->difference_cents !== 0 && $row->adjustment_entry_id === null,
                    'createdBy' => $row->creator->name ?? null,
                    'canAdjust' => $actor->can('adjust', $row),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(ReconciliationRequest $request, SaveReconciliation $save): RedirectResponse
    {
        $this->authorize('create', Reconciliation::class);

        $validated = $request->validated();

        $reconciliation = $save->handle(
            $this->actor($request),
            (int) $validated['account_id'],
            (string) $validated['as_of'],
            (int) $validated['counted_balance_cents'],
            $validated['note'] ?? null,
            (bool) ($validated['record_difference'] ?? false),
            $validated['reason'] ?? null,
        );

        return to_route('reconciliations.index', ['account' => $reconciliation->account_id], 303)
            ->with('success', $reconciliation->difference_cents === 0
                ? __('Reconciled: the balance matches.')
                : ($reconciliation->adjustment_entry_id !== null ? __('Reconciled and the difference was recorded.') : __('Saved. The difference is left open to investigate.')));
    }

    /** Turn a difference that was left open into an adjustment entry. */
    public function adjust(ReversalRequest $request, Reconciliation $reconciliation, SaveReconciliation $save): RedirectResponse
    {
        $reconciliation->loadMissing('account:id,office_id');
        $this->authorize('adjust', $reconciliation);

        $save->recordOpenDifference($this->actor($request), $reconciliation, (string) $request->validated('reason'));

        return back(303)->with('success', __('The difference was recorded.'));
    }

    private function date(string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $fallback;
        }

        $date = CarbonImmutable::parse($value)->startOfDay();

        return $date->gt($fallback) ? $fallback : $date;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
