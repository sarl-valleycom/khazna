<?php

namespace App\Actions\Ledger;

use App\Actions\Payments\StorePaymentReceipt;
use App\Enums\CategoryKind;
use App\Enums\Direction;
use App\Models\Account;
use App\Models\Category;
use App\Models\ManualEntry;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecordManualEntry
{
    public function __construct(
        private readonly LedgerWriter $ledger,
        private readonly StorePaymentReceipt $storeReceipt,
    ) {}

    /**
     * Money with no due behind it: bank fees, taxes, petty cash, an owner draw or an
     * injection (FR-ACC-09). Needs an account, a direction, a category of the matching
     * kind and a description; a receipt is optional.
     */
    public function handle(User $actor, int $accountId, Direction $direction, int $amountCents, string $date, int $categoryId, string $description, ?UploadedFile $receipt): ManualEntry
    {
        $description = trim($description);

        if ($description === '') {
            throw ValidationException::withMessages(['description' => __('Enter a description.')]);
        }

        $on = AlgerianDate::toAlgiers($date)->startOfDay();

        if ($on->gt(AlgerianDate::today())) {
            throw ValidationException::withMessages(['entry_date' => __('The date cannot be in the future.')]);
        }

        $stored = $receipt !== null ? $this->storeReceipt->handle($receipt) : [];

        try {
            return DB::transaction(function () use ($actor, $accountId, $direction, $amountCents, $on, $categoryId, $description, $stored): ManualEntry {
                $account = Account::query()->visibleTo($actor)->where('active', true)->lockForUpdate()->find($accountId)
                    ?? throw ValidationException::withMessages(['account_id' => __('Choose one of the accounts available to you.')]);

                // Money in is filed under an income category, money out under a cost one.
                $kind = $direction === Direction::In ? CategoryKind::Income : CategoryKind::Cost;
                $category = Category::query()->where('active', true)->where('kind', $kind)->find($categoryId)
                    ?? throw ValidationException::withMessages(['category_id' => __('Choose a :kind category.', ['kind' => strtolower($kind->label())])]);

                $entry = ManualEntry::query()->create([
                    ...$stored,
                    'account_id' => $account->id,
                    'direction' => $direction,
                    'amount_cents' => $amountCents,
                    'entry_date' => $on->toDateString(),
                    'category_id' => $category->id,
                    'description' => $description,
                    'created_by' => $actor->id,
                ]);

                $this->ledger->record($account, $direction, $amountCents, $on, $entry, $actor, $description, 'entry_date', categoryId: $category->id, isPayroll: $category->kind === CategoryKind::Cost && $category->name === Category::SALARIES);

                return $entry;
            });
        } catch (Throwable $exception) {
            if (isset($stored['receipt_path'])) {
                Storage::disk('local')->delete($stored['receipt_path']);
            }

            throw $exception;
        }
    }
}
