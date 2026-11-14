<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /** Receipts are images or PDFs, checked against the file's real content. */
    public const RECEIPT_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public const RECEIPT_MAX_KB = 5120;

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
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000000'],
            'account_id' => ['required', 'integer'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => [
                'nullable',
                'file',
                'max:'.self::RECEIPT_MAX_KB,
                'mimetypes:'.implode(',', self::RECEIPT_MIMES),
            ],
            'confirm_overpayment' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_cents.min' => __('Enter an amount greater than zero.'),
            'amount_cents.required' => __('Enter the amount received.'),
            'account_id.required' => __('Choose the account the money went into.'),
            'paid_on.required' => __('Choose the payment date.'),
            'receipt.mimetypes' => __('The receipt must be an image (JPG, PNG, WebP) or a PDF.'),
            'receipt.max' => __('The receipt must be 5 MB or smaller.'),
        ];
    }
}
