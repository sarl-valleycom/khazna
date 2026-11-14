<?php

namespace App\Actions\Payments;

use App\Http\Requests\Payments\PaymentRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StorePaymentReceipt
{
    /**
     * Store on the private disk under a random name. The type is taken from the
     * file's content, never from the client-supplied name or header.
     *
     * @return array{receipt_path: string, receipt_mime: string}
     */
    public function handle(UploadedFile $file): array
    {
        $mime = $file->getMimeType();

        if ($mime === null || ! in_array($mime, PaymentRequest::RECEIPT_MIMES, true)) {
            throw ValidationException::withMessages([
                'receipt' => __('The receipt must be an image (JPG, PNG, WebP) or a PDF.'),
            ]);
        }

        $path = Storage::disk('local')->putFile('receipts', $file);

        if ($path === false) {
            throw ValidationException::withMessages([
                'receipt' => __('The receipt could not be stored.'),
            ]);
        }

        return ['receipt_path' => $path, 'receipt_mime' => $mime];
    }
}
