<?php

namespace App\Http\Requests\Ledger;

use App\Enums\Direction;
use App\Http\Requests\Payments\PaymentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualEntryRequest extends FormRequest
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
        return [
            'account_id' => ['required', 'integer'],
            'direction' => ['required', Rule::enum(Direction::class)],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'max:'.PaymentRequest::RECEIPT_MAX_KB, 'mimetypes:'.implode(',', PaymentRequest::RECEIPT_MIMES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => __('Choose the account.'),
            'category_id.required' => __('Choose a category.'),
            'description.required' => __('Enter a description.'),
            'amount_cents.min' => __('Enter an amount greater than zero.'),
            'entry_date.required' => __('Choose the date.'),
            'receipt.mimetypes' => __('The receipt must be an image (JPG, PNG, WebP) or a PDF.'),
            'receipt.max' => __('The receipt must be 5 MB or smaller.'),
        ];
    }
}
