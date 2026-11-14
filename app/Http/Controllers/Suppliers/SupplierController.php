<?php

namespace App\Http\Controllers\Suppliers;

use App\Enums\ContractStatus;
use App\Enums\DueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $term = trim($request->string('q')->toString());

        $suppliers = Supplier::query()
            ->withCount([
                'contracts as live_contracts_count' => fn ($query) => $query->whereIn('status', [ContractStatus::Active->value, ContractStatus::Paused->value]),
                'dues as open_dues_count' => fn ($query) => $query->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value]),
            ])
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact' => $supplier->contact,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'notes' => $supplier->notes,
                'liveContracts' => (int) $supplier->getAttribute('live_contracts_count'),
                'openDues' => (int) $supplier->getAttribute('open_dues_count'),
            ])
            ->values()
            ->all();

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['q' => $term],
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        Supplier::query()->create($request->validated());

        return back(303)->with('success', __('Supplier created.'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return back(303)->with('success', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $stillInUse = $supplier->contracts()->whereIn('status', [ContractStatus::Active->value, ContractStatus::Paused->value])->exists()
            || $supplier->dues()->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])->exists();

        if ($stillInUse) {
            return back(303)->with('error', __('This supplier still has open dues or live contracts. Cancel the contracts and settle or cancel the dues first.'));
        }

        $supplier->delete();

        return back(303)->with('success', __('Supplier deleted.'));
    }
}
