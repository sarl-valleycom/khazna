<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Enums\Direction;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money that moved with no due behind it: bank fees, taxes, petty cash, an owner
 * draw or injection.
 *
 * @property int $id
 * @property int $account_id
 * @property Direction $direction
 * @property int $amount_cents
 * @property CarbonImmutable $entry_date
 * @property int $category_id
 * @property string $description
 * @property string|null $receipt_path
 * @property string|null $receipt_mime
 * @property int|null $created_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property int|null $reversed_by
 */
#[Fillable(['account_id', 'direction', 'amount_cents', 'entry_date', 'category_id', 'description', 'receipt_path', 'receipt_mime', 'created_by', 'reversed_at', 'reversal_reason', 'reversed_by'])]
class ManualEntry extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'direction' => Direction::class,
            'amount_cents' => 'integer',
            'entry_date' => 'immutable_date',
            'category_id' => 'integer',
            'created_by' => 'integer',
            'reversed_at' => 'immutable_datetime',
            'reversed_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Money filed under Salaries is payroll, wherever it was recorded from. Needs the category loaded. */
    public function isPayroll(): bool
    {
        $category = $this->category;

        return $category !== null && $category->name === Category::SALARIES && $category->kind === CategoryKind::Cost;
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
