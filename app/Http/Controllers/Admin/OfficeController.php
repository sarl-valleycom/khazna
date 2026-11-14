<?php

namespace App\Http\Controllers\Admin;

use App\Data\OfficeRowData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfficeRequest;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Office::class);

        $offices = Office::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office): OfficeRowData => OfficeRowData::fromOffice($office))
            ->all();

        return Inertia::render('admin/Offices', [
            'offices' => $offices,
        ]);
    }

    public function store(OfficeRequest $request): RedirectResponse
    {
        $this->authorize('create', Office::class);

        Office::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'city' => $request->string('city')->trim()->toString(),
            'active' => $request->boolean('active', true),
        ]);

        return to_route('offices.index', [], 303)->with('success', __('Office created.'));
    }

    public function update(OfficeRequest $request, Office $office): RedirectResponse
    {
        $this->authorize('update', $office);

        $active = $request->boolean('active', true);

        if ($office->active && ! $active && Office::query()->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'active' => __('At least one active office is required.'),
            ]);
        }

        $office->update([
            'name' => $request->string('name')->trim()->toString(),
            'city' => $request->string('city')->trim()->toString(),
            'active' => $active,
        ]);

        return to_route('offices.index', [], 303)->with('success', __('Office updated.'));
    }
}
