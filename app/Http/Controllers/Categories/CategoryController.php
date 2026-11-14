<?php

namespace App\Http\Controllers\Categories;

use App\Enums\CategoryKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        return Inertia::render('categories/Index', [
            'categories' => Category::query()
                ->orderBy('kind')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'kind' => $category->kind->value,
                    'kindLabel' => $category->kind->label(),
                    'active' => $category->active,
                    'system' => $category->isSystem(),
                ])
                ->all(),
            'kinds' => collect(CategoryKind::cases())
                ->map(fn (CategoryKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()])
                ->all(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        Category::query()->create([...$request->validated(), 'active' => true]);

        return back(303)->with('success', __('Category created.'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        if ($category->isSystem()) {
            $data = $request->validated();

            if (($data['name'] ?? $category->name) !== $category->name || ($data['active'] ?? true) === false) {
                return back(303)->with('error', __('This category is used by the system. It cannot be renamed or deactivated.'));
            }
        }

        $category->update($request->validated());

        return back(303)->with('success', __('Category updated.'));
    }
}
