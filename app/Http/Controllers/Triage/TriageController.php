<?php

namespace App\Http\Controllers\Triage;

use App\Data\AccountManagerOptionData;
use App\Data\AttentionContractData;
use App\Data\ContractClientOptionData;
use App\Data\DueRowData;
use App\Data\OfficeOptionData;
use App\Data\TriageFiltersData;
use App\Data\TriageTabData;
use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Due;
use App\Models\Office;
use App\Models\User;
use App\Queries\DueTriageQuery;
use App\Queries\PaymentAccountOptionsQuery;
use App\Support\AlgerianDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TriageController extends Controller
{
    public function __invoke(
        Request $request,
        DueTriageQuery $triage,
        PaymentAccountOptionsQuery $accountOptions,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $filters = TriageFiltersData::fromRequest($request);
        $today = AlgerianDate::today();

        $summary = $triage->summary($actor, $filters, $today);
        $attention = $triage->attention($actor, $filters, $today);

        $labels = [
            'overdue' => 'Overdue',
            'today' => 'Today',
            'week' => 'This week',
            'month' => 'This month',
        ];

        $tabs = [];

        foreach ($labels as $value => $label) {
            $tabs[] = new TriageTabData(
                value: $value,
                label: $label,
                count: $summary[$value]['count'],
                collectCents: $summary[$value]['collectCents'],
                payCents: $summary[$value]['payCents'],
            );
        }

        $tabs[] = new TriageTabData('attention', 'Needs attention', $attention->count(), null, null);

        $rows = $filters->tab === 'attention'
            ? null
            : $triage->rows($actor, $filters, $today);

        $clientIds = $rows === null
            ? []
            : array_values(array_unique(array_filter(array_map(
                fn (Due $due): ?int => $due->client_id,
                $rows->items(),
            ))));
        $paymentOptions = $accountOptions->handle($actor, $clientIds);

        return Inertia::render('triage/Index', [
            'filters' => $filters,
            'tabs' => $tabs,
            'dues' => $rows?->through(fn (Due $due): DueRowData => DueRowData::fromDue($due, $actor)),
            'attention' => $filters->tab === 'attention'
                ? $attention->map(fn (array $item): AttentionContractData => AttentionContractData::fromContract($item['contract'], $item['reason']))->all()
                : [],
            'weekEnd' => $triage->bounds($today)[0]->toDateString(),
            'paymentAccounts' => $paymentOptions['accounts'],
            'paymentDefaults' => $paymentOptions['defaults'],
            'clients' => Client::query()
                ->visibleTo($actor)
                ->with('office:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Client $client): ContractClientOptionData => ContractClientOptionData::fromClient($client))
                ->all(),
            // Not `offices`: that name is the shared prop the office switcher reads.
            'officeOptions' => $actor->office_id === null
                ? Office::query()->where('active', true)->orderBy('name')->get()
                    ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData($office->id, $office->name, $office->city))
                    ->all()
                : [],
            'assignees' => $actor->isAccountManager()
                ? []
                : User::query()
                    ->with('office:id,name')
                    ->where('role', Role::AccountManager)
                    ->when($actor->office_id !== null, fn ($query) => $query->where('office_id', $actor->office_id))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $user): AccountManagerOptionData => AccountManagerOptionData::fromUser($user))
                    ->all(),
            // Salary is not even offered as a filter to anyone who may not see salary dues.
            'kinds' => collect(DueKind::cases())
                ->reject(fn (DueKind $kind): bool => $kind === DueKind::Salary && ! $actor->can('view-salaries'))
                ->map(fn (DueKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()])
                ->all(),
            'directions' => collect(Direction::cases())
                ->map(fn (Direction $direction): array => ['value' => $direction->value, 'label' => $direction->label()])
                ->all(),
        ]);
    }
}
