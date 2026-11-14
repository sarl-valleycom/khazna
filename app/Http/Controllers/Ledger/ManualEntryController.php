<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Ledger\ReverseManualEntry;
use App\Data\PaymentAccountData;
use App\Enums\Direction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\ManualEntryRequest;
use App\Http\Requests\Ledger\ReversalRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\ManualEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ManualEntry::class);

        $actor = $this->actor($request);

        $entries = ManualEntry::query()
            ->with(['account' => fn ($account) => $account->select('id', 'name', 'office_id')->with('office:id,name'), 'category:id,name', 'creator:id,name'])
            ->whereHas('account', fn ($account) => $account->visibleTo($actor))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return Inertia::render('accounts/Entries', [
            'entries' => $entries->map(fn (ManualEntry $entry): array => [
                'id' => $entry->id,
                'date' => $entry->entry_date->toDateString(),
                'accountName' => $entry->account->name ?? '',
                'officeName' => $entry->account->office->name ?? '',
                'direction' => $entry->direction->value,
                'amountCents' => $entry->amount_cents,
                'categoryName' => $entry->category->name ?? '',
                'description' => $entry->description,
                'hasReceipt' => $entry->receipt_path !== null,
                'reversed' => $entry->isReversed(),
                'reversalReason' => $entry->reversal_reason,
                'createdBy' => $entry->creator->name ?? null,
                'canReverse' => $actor->can('reverse', $entry),
            ])->values()->all(),
            'accounts' => Account::query()->visibleTo($actor)->where('active', true)->with('office:id,name')->orderBy('office_id')->orderBy('name')->get()
                ->map(fn (Account $account): PaymentAccountData => PaymentAccountData::fromAccount($account))->values()->all(),
            'categories' => Category::query()->where('active', true)->orderBy('kind')->orderBy('name')->get()
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'kind' => $category->kind->value])->values()->all(),
        ]);
    }

    public function store(ManualEntryRequest $request, RecordManualEntry $record): RedirectResponse
    {
        $this->authorize('create', ManualEntry::class);

        $validated = $request->validated();

        $record->handle(
            $this->actor($request),
            (int) $validated['account_id'],
            Direction::from((string) $validated['direction']),
            (int) $validated['amount_cents'],
            (string) $validated['entry_date'],
            (int) $validated['category_id'],
            (string) $validated['description'],
            $request->file('receipt'),
        );

        return back(303)->with('success', __('Entry recorded.'));
    }

    public function reverse(ReversalRequest $request, ManualEntry $entry, ReverseManualEntry $reverse): RedirectResponse
    {
        $entry->loadMissing('account:id,office_id');
        $this->authorize('reverse', $entry);

        $reverse->handle($this->actor($request), $entry, (string) $request->validated('reason'));

        return back(303)->with('success', __('Entry reversed.'));
    }

    public function receipt(ManualEntry $entry): StreamedResponse
    {
        $entry->loadMissing('account:id,office_id');
        $this->authorize('view', $entry);

        abort_if($entry->receipt_path === null || $entry->receipt_mime === null, 404);
        abort_unless(Storage::disk('local')->exists($entry->receipt_path), 404);

        return Storage::disk('local')->response($entry->receipt_path, "entry-{$entry->id}", [
            'Content-Type' => $entry->receipt_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
