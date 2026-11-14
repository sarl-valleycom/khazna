<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Ledger\RecordTransfer;
use App\Actions\Ledger\ReverseTransfer;
use App\Data\PaymentAccountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ReversalRequest;
use App\Http\Requests\Ledger\TransferRequest;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transfer::class);

        $actor = $this->actor($request);

        $transfers = Transfer::query()
            ->with(['from.office:id,name', 'to.office:id,name', 'creator:id,name'])
            ->whereHas('from', fn ($account) => $account->visibleTo($actor))
            ->whereHas('to', fn ($account) => $account->visibleTo($actor))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return Inertia::render('accounts/Transfers', [
            'transfers' => $transfers->map(fn (Transfer $transfer): array => [
                'id' => $transfer->id,
                'date' => $transfer->transfer_date->toDateString(),
                'fromName' => $transfer->from->name ?? '',
                'fromOffice' => $transfer->from->office->name ?? '',
                'toName' => $transfer->to->name ?? '',
                'toOffice' => $transfer->to->office->name ?? '',
                'amountCents' => $transfer->amount_cents,
                'feeCents' => $transfer->fee_cents,
                'note' => $transfer->note,
                'interOffice' => $transfer->isInterOffice(),
                'reversed' => $transfer->isReversed(),
                'reversalReason' => $transfer->reversal_reason,
                'createdBy' => $transfer->creator->name ?? null,
                'canReverse' => $actor->can('reverse', $transfer),
            ])->values()->all(),
            'accounts' => $this->accounts($actor),
        ]);
    }

    public function store(TransferRequest $request, RecordTransfer $record): RedirectResponse
    {
        $this->authorize('create', Transfer::class);

        $record->handle(
            $this->actor($request),
            (int) $request->validated('from_account_id'),
            (int) $request->validated('to_account_id'),
            (int) $request->validated('amount_cents'),
            (int) ($request->validated('fee_cents') ?? 0),
            (string) $request->validated('transfer_date'),
            $request->validated('note'),
        );

        return back(303)->with('success', __('Transfer recorded.'));
    }

    public function reverse(ReversalRequest $request, Transfer $transfer, ReverseTransfer $reverse): RedirectResponse
    {
        $transfer->loadMissing(['from:id,office_id', 'to:id,office_id']);
        $this->authorize('reverse', $transfer);

        $reverse->handle($this->actor($request), $transfer, (string) $request->validated('reason'));

        return back(303)->with('success', __('Transfer reversed.'));
    }

    /**
     * @return array<int, PaymentAccountData>
     */
    private function accounts(User $actor): array
    {
        return Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->with('office:id,name')
            ->orderBy('office_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): PaymentAccountData => PaymentAccountData::fromAccount($account))
            ->values()
            ->all();
    }
}
