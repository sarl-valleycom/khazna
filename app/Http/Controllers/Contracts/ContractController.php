<?php

namespace App\Http\Controllers\Contracts;

use App\Actions\Contracts\ChangeContractStatus;
use App\Actions\Contracts\CreateContract;
use App\Actions\Contracts\UpdateContract;
use App\Data\ContractClientOptionData;
use App\Data\ContractData;
use App\Data\ContractFiltersData;
use App\Data\ContractInput;
use App\Data\OfficeOptionData;
use App\Enums\ContractStatus;
use App\Enums\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractRequest;
use App\Http\Requests\Contracts\ContractStatusRequest;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Office;
use App\Models\User;
use App\Queries\ContractIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function index(Request $request, ContractIndexQuery $query): Response
    {
        $this->authorize('viewAny', Contract::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $filters = ContractFiltersData::fromRequest($request);
        $contracts = $query->handle($actor, $filters);

        return Inertia::render('contracts/Index', [
            'contracts' => $contracts->getCollection()
                ->map(fn (Contract $contract): ContractData => ContractData::fromContract($contract, $actor))
                ->values()
                ->all(),
            'total' => $contracts->total(),
            'page' => $contracts->currentPage(),
            'perPage' => $contracts->perPage(),
            'sort' => $filters->sort,
            'direction' => $filters->direction,
            'filters' => $filters,
            'clients' => $this->clients($actor),
            'offices' => $this->offices($actor),
            'statuses' => $this->statuses(),
            'cycles' => $this->cycles(),
            'canChooseOffice' => $this->canChooseOffice($actor),
        ]);
    }

    public function show(Request $request, Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $contract->load(['client:id,name,account_manager_id', 'office:id,name']);

        return Inertia::render('contracts/Show', [
            'contract' => ContractData::fromContract($contract, $actor),
            'clients' => $this->clients($actor),
            'offices' => $this->offices($actor),
            'cycles' => $this->cycles(),
            'canChooseOffice' => $this->canChooseOffice($actor),
        ]);
    }

    public function store(ContractRequest $request, CreateContract $create): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $contract = $create->handle($actor, ContractInput::fromRequest($request));

        return to_route('contracts.show', $contract, 303)->with('success', __('Contract created.'));
    }

    public function update(ContractRequest $request, Contract $contract, UpdateContract $update): RedirectResponse
    {
        $this->authorize('update', $contract);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $update->handle($contract, $actor, ContractInput::fromRequest($request));

        return to_route('contracts.show', $contract, 303)->with('success', __('Contract updated.'));
    }

    public function status(ContractStatusRequest $request, Contract $contract, ChangeContractStatus $change): RedirectResponse
    {
        $this->authorize('changeStatus', $contract);

        $validated = $request->validated();

        $change->handle(
            $contract,
            ContractStatus::from($validated['status']),
            $validated['reason'] ?? null,
        );

        return back(303)->with('success', __('Contract status updated.'));
    }

    public function attachment(Contract $contract): StreamedResponse
    {
        $this->authorize('view', $contract);

        $path = $contract->attachment_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response(
            $path,
            $contract->attachment_name ?? 'contract-attachment',
            [
                'Content-Type' => $contract->attachment_mime ?? 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /**
     * @return list<ContractClientOptionData>
     */
    private function clients(User $actor): array
    {
        $clients = Client::query()
            ->visibleTo($actor)
            ->with('office:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client): ContractClientOptionData => ContractClientOptionData::fromClient($client))
            ->all();

        return array_values($clients);
    }

    /**
     * @return list<OfficeOptionData>
     */
    private function offices(User $actor): array
    {
        $offices = Office::query()
            ->visibleTo($actor)
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData(
                id: $office->id,
                name: $office->name,
                city: $office->city,
            ))
            ->all();

        return array_values($offices);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return array_map(fn (ContractStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], ContractStatus::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function cycles(): array
    {
        return array_map(fn (Cycle $cycle): array => [
            'value' => $cycle->value,
            'label' => $cycle->label(),
        ], [Cycle::Monthly, Cycle::Quarterly, Cycle::Yearly]);
    }

    private function canChooseOffice(User $actor): bool
    {
        return ! $actor->isAccountManager() && $actor->office_id === null;
    }
}
