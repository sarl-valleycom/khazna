<?php

namespace App\Http\Controllers\Accounts;

use App\Actions\Payments\SaveAccount;
use App\Data\AccountRowData;
use App\Data\AccountStatementRowData;
use App\Data\OfficeOptionData;
use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\AccountRequest;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Office;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $actor = $this->actor($request);

        $accounts = Account::query()
            ->visibleTo($actor)
            ->withBalance()
            ->with('office:id,name')
            ->orderBy('office_id')
            ->orderBy('name')
            ->get();

        $locked = AccountEntry::query()
            ->whereIn('account_id', $accounts->modelKeys())
            ->distinct()
            ->pluck('account_id')
            ->all();

        $groups = $accounts
            ->groupBy('office_id')
            ->map(function ($officeAccounts, $officeId) use ($actor, $locked): array {
                $total = (int) $officeAccounts->where('active', true)->sum('balance_cents');

                return [
                    'officeId' => (int) $officeId,
                    'officeName' => $officeAccounts->first()?->office->name ?? '',
                    'totalCents' => $total,
                    'totalFormatted' => Money::fromCents($total)->format(),
                    'accounts' => $officeAccounts
                        ->map(fn (Account $account): AccountRowData => AccountRowData::fromAccount(
                            $account,
                            $actor,
                            in_array($account->id, $locked, true),
                        ))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $grand = (int) $accounts->where('active', true)->sum('balance_cents');

        return Inertia::render('accounts/Index', [
            'groups' => $groups,
            'grandTotalCents' => $grand,
            'grandTotalFormatted' => Money::fromCents($grand)->format(),
            'canCreate' => $actor->can('create', Account::class),
            'offices' => Office::query()
                ->where('active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData($office->id, $office->name, $office->city))
                ->all(),
            'types' => collect(AccountType::cases())
                ->map(fn (AccountType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function show(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        $actor = $this->actor($request);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $signed = "CASE WHEN account_entries.direction = 'in' THEN account_entries.amount_cents ELSE -account_entries.amount_cents END";

        // Entries before the range move the starting balance; the window sum then
        // runs only over the rows shown, so the running balance stays correct on any page.
        $before = $from === null ? 0 : (int) AccountEntry::query()
            ->where('account_id', $account->id)
            ->where('entry_date', '<', $from)
            ->sum(DB::raw($signed));
        $start = $account->opening_balance_cents + $before;

        $inRange = AccountEntry::query()
            ->where('account_id', $account->id)
            ->when($from !== null, fn ($query) => $query->where('entry_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('entry_date', '<=', $to));

        $totals = (clone $inRange)->selectRaw(
            "COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE 0 END), 0) AS total_in,
             COALESCE(SUM(CASE WHEN direction = 'out' THEN amount_cents ELSE 0 END), 0) AS total_out",
        )->first();
        $totalIn = (int) ($totals->total_in ?? 0);
        $totalOut = (int) ($totals->total_out ?? 0);

        // Payroll is hidden from anyone without the salary permission: all of a day's
        // payroll movements in one direction collapse into a single "Payroll" line,
        // so neither a name nor an individual salary shows. Balances still add up.
        $source = $actor->can('view-salaries')
            ? (clone $inRange)
            : AccountEntry::query()->fromSub(
                (clone $inRange)->toBase()
                    ->selectRaw("MIN(id) AS id, account_id, direction, SUM(amount_cents) AS amount_cents, entry_date, CASE WHEN is_payroll = 1 THEN 'Payroll' ELSE MAX(description) END AS description")
                    ->groupByRaw('account_id, entry_date, direction, is_payroll, CASE WHEN is_payroll = 1 THEN 0 ELSE id END'),
                'account_entries',
            );

        $entries = $source
            ->select('account_entries.*')
            ->selectRaw("? + SUM({$signed}) OVER (ORDER BY account_entries.entry_date, account_entries.id) AS running_cents", [$start])
            ->orderBy('account_entries.entry_date')
            ->orderBy('account_entries.id')
            ->paginate(50)
            ->withQueryString();

        $account->load('office:id,name');
        $account->balance_cents = $account->balanceCents();

        return Inertia::render('accounts/Show', [
            'account' => AccountRowData::fromAccount($account, $actor, $account->hasEntries()),
            'entries' => $entries->through(fn (AccountEntry $entry): AccountStatementRowData => AccountStatementRowData::fromEntry($entry)),
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'startCents' => $start,
                'startFormatted' => Money::fromCents($start)->format(),
                'totalInFormatted' => Money::fromCents($totalIn)->format(),
                'totalOutFormatted' => Money::fromCents($totalOut)->format(),
                'endCents' => $start + $totalIn - $totalOut,
                'endFormatted' => Money::fromCents($start + $totalIn - $totalOut)->format(),
            ],
            'types' => collect(AccountType::cases())
                ->map(fn (AccountType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function store(AccountRequest $request, SaveAccount $save): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $save->create($request->validated());

        return back(303)->with('success', __('Account created.'));
    }

    public function update(AccountRequest $request, Account $account, SaveAccount $save): RedirectResponse
    {
        $this->authorize('update', $account);

        $save->update($account, $request->validated());

        return back(303)->with('success', __('Account updated.'));
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
