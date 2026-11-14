<?php

namespace App\Http\Controllers\Clients;

use App\Data\AccountManagerOptionData;
use App\Data\ClientFiltersData;
use App\Data\ClientRowData;
use App\Data\ClientShowData;
use App\Data\OfficeOptionData;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Queries\ClientIndexQuery;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request, ClientIndexQuery $query): Response
    {
        $this->authorize('viewAny', Client::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $filters = ClientFiltersData::fromRequest($request);
        $clients = $query->handle($actor, $filters);

        return Inertia::render('clients/Index', [
            'clients' => $clients->getCollection()
                ->map(fn (Client $client): ClientRowData => ClientRowData::fromClient($client, $actor))
                ->values()
                ->all(),
            'total' => $clients->total(),
            'page' => $clients->currentPage(),
            'perPage' => $clients->perPage(),
            'sort' => $filters->sort,
            'direction' => $filters->direction,
            'filters' => $filters,
            'managers' => $this->managers($actor),
            'offices' => $this->offices($actor),
            'canAssignManager' => $actor->isOwner() || $actor->isFinance(),
            'canImport' => $actor->can('import', Client::class),
        ]);
    }

    public function show(Request $request, Client $client): Response
    {
        $this->authorize('view', $client);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $client->load(['accountManager:id,name,email,office_id', 'office:id,name']);

        return Inertia::render('clients/Show', [
            'client' => ClientShowData::fromClient($client, $actor),
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        Client::query()->create($this->attributes($request, $actor));

        return to_route('clients.index', [], 303)->with('success', __('Client created.'));
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $client->update($this->attributes($request, $actor));

        return to_route('clients.show', $client, 303)->with('success', __('Client updated.'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return to_route('clients.index', [], 303)->with('success', __('Client deleted.'));
    }

    /**
     * @return array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}
     */
    private function attributes(ClientRequest $request, User $actor): array
    {
        $validated = $request->validated();
        $manager = User::query()->whereKey($validated['account_manager_id'])->firstOrFail();

        if ($actor->isAccountManager()) {
            $manager = $actor;
        }

        if ($actor->office_id !== null
            && $manager->office_id !== null
            && $manager->office_id !== $actor->office_id) {
            throw ValidationException::withMessages([
                'account_manager_id' => __('Choose an account manager from your office.'),
            ]);
        }

        $officeId = $actor->office_id ?? $validated['office_id'] ?? $manager->office_id;

        if ($manager->office_id !== null && $officeId !== $manager->office_id) {
            throw ValidationException::withMessages([
                'office_id' => __("The client must be in the account manager's office."),
            ]);
        }

        $phone = Phone::normalize($validated['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => __('Enter a valid Algerian mobile number, for example 0550 12 34 56.'),
            ]);
        }

        return [
            'name' => trim($validated['name']),
            'contact' => $this->nullable($validated['contact'] ?? null),
            'phone' => $phone,
            'email' => $this->nullable($validated['email'] ?? null),
            'address' => $this->nullable($validated['address'] ?? null),
            'notes' => $this->nullable($validated['notes'] ?? null),
            'account_manager_id' => $manager->id,
            'office_id' => $officeId,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<AccountManagerOptionData>
     */
    private function managers(User $actor): array
    {
        if ($actor->isAccountManager()) {
            $actor->loadMissing('office:id,name');

            return [AccountManagerOptionData::fromUser($actor)];
        }

        $managers = User::query()
            ->with('office:id,name')
            ->where('role', Role::AccountManager->value)
            ->when(
                $actor->office_id !== null,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query->whereNull('office_id')->orWhere('office_id', $actor->office_id),
                ),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (User $manager): AccountManagerOptionData => AccountManagerOptionData::fromUser($manager))
            ->all();

        return array_values($managers);
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
}
