<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryKind;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $editing = $category instanceof Category;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where('kind', $editing ? $category->kind->value : $this->string('kind')->toString())
                    ->ignore($editing ? $category->id : null),
            ],
            // A category's kind is fixed once created: money already filed under it would change meaning.
            'kind' => [$editing ? 'prohibited' : 'required', Rule::enum(CategoryKind::class)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('There is already a category with that name.'),
            'kind.prohibited' => __('The kind of a category cannot change.'),
        ];
    }
}
