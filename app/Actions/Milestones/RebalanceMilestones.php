<?php

namespace App\Actions\Milestones;

use App\Models\Contract;
use App\Models\Due;
use App\Models\Milestone;
use App\Support\AuditContext;
use App\Support\MilestonePlan;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RebalanceMilestones
{
    /**
     * Re-split a project's unpaid milestones. A milestone that already has a payment
     * keeps its amount: only the rest of the contract is re-shared among the others.
     *
     * With $apply false nothing is written and the new amounts are returned, so the
     * screen can show the change before the user confirms (FR-MIL-04).
     *
     * @param  list<array{id: int, label?: string, mode?: string, value: int|float|string|null, due_on?: string|null}>  $rows  Only the unpaid milestones, in the order to keep.
     * @return array<int, array{id: int, label: string, oldCents: int, newCents: int, locked: bool}>
     */
    public function handle(Contract $contract, array $rows, bool $apply): array
    {
        $milestones = $contract->milestones()->with('due')->get()->keyBy('id');
        $find = fn (int $id): Milestone => $milestones->get($id) ?? throw ValidationException::withMessages([
            'milestones' => __('That milestone does not belong to this contract.'),
        ]);

        $locked = $milestones->filter(fn (Milestone $milestone): bool => $milestone->due !== null && $milestone->due->amount_paid_cents > 0);
        $lockedCents = (int) $locked->sum('amount_cents');
        $remaining = $contract->amount_cents - $lockedCents;

        $submittedIds = array_map(fn (array $row): int => (int) $row['id'], $rows);
        $expectedIds = $milestones->keys()->diff($locked->keys())->values()->all();

        if (collect($submittedIds)->sort()->values()->all() !== collect($expectedIds)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'milestones' => __('Send exactly the milestones that have no payment yet.'),
            ]);
        }

        $amounts = MilestonePlan::amounts($rows, $remaining);

        $plan = [];

        foreach ($locked as $milestone) {
            $plan[$milestone->id] = ['id' => $milestone->id, 'label' => $milestone->label, 'oldCents' => $milestone->amount_cents, 'newCents' => $milestone->amount_cents, 'locked' => true];
        }

        foreach ($rows as $index => $row) {
            $milestone = $find((int) $row['id']);
            $plan[$milestone->id] = [
                'id' => $milestone->id,
                'label' => trim((string) ($row['label'] ?? $milestone->label)),
                'oldCents' => $milestone->amount_cents,
                'newCents' => $amounts[$index],
                'locked' => false,
            ];
        }

        $ordered = $milestones->keys()->map(fn (int $id): array => $plan[$id])->values()->all();

        if (! $apply) {
            return $ordered;
        }

        DB::transaction(function () use ($rows, $find, $plan): void {
            foreach ($rows as $row) {
                $milestone = $find((int) $row['id']);
                $due = Due::query()->lockForUpdate()->where('milestone_id', $milestone->id)->firstOrFail();
                $newCents = $plan[$milestone->id]['newCents'];
                $label = $plan[$milestone->id]['label'];

                app(AuditContext::class)->withReason(__('Milestones rebalanced'), function () use ($milestone, $due, $newCents, $label, $row): void {
                    $milestone->update(['amount_cents' => $newCents, 'label' => $label]);
                    $due->update(['amount_due_cents' => $newCents, 'description' => $label]);

                    // A dated milestone may also move; a delivered or delivery-triggered one keeps its date.
                    if (array_key_exists('due_on', $row) && $row['due_on'] !== null && $milestone->delivered_at === null && $milestone->trigger->value === 'date') {
                        $milestone->update(['due_on' => $row['due_on']]);
                        $due->update(['due_on' => $row['due_on']]);
                    }
                });
            }
        });

        return $ordered;
    }

    /** Human text for a plan line, for messages and tests. */
    public static function describe(int $cents): string
    {
        return Money::fromCents($cents)->format();
    }
}
