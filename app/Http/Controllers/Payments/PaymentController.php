<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Data\PaymentInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PaymentRequest;
use App\Http\Requests\Payments\ReversePaymentRequest;
use App\Models\Due;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Due $due, RecordPayment $record): RedirectResponse
    {
        $due->loadMissing('client:id,name,account_manager_id');
        $this->authorize('pay', $due);

        $record->handle($this->actor($request), $due, PaymentInput::fromRequest($request));

        return back(303)->with('success', __('Payment recorded.'));
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment, ReversePayment $reverse): RedirectResponse
    {
        $payment->loadMissing('due.client:id,name,account_manager_id');
        $this->authorize('reverse', $payment);

        $reverse->handle($this->actor($request), $payment, (string) $request->validated('reason'));

        return back(303)->with('success', __('Payment reversed.'));
    }

    public function receipt(Request $request, Payment $payment): StreamedResponse
    {
        $payment->loadMissing('due.client:id,name,account_manager_id');
        $this->authorize('view', $payment);

        abort_if($payment->receipt_path === null || $payment->receipt_mime === null, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($payment->receipt_path), 404);

        $extension = match ($payment->receipt_mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return $disk->response($payment->receipt_path, "receipt-{$payment->id}.{$extension}", [
            // The type comes from what we verified at upload, never from the file name.
            'Content-Type' => $payment->receipt_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
