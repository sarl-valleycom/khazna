<?php

namespace App\Actions\Milestones;

use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Enums\MilestoneTrigger;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Milestone;
use App\Support\MilestonePlan;
use Illuminate\Validation\ValidationException;

class CreateMilestones
{
    /**
     * Create a project contract's milestones and one due for each (FR-MIL-01): a
     * dated milestone is due on its date, a delivery milestone has no date until
     * it is delivered. Call inside the contract's transaction.
     *
     * @param  list<array{label: string, mode?: string, value: int|float|string|null, trigger?: string, due_on?: string|null}>  $rows
     * @return list<Milestone>
     */
    public function handle(Contract $contract, array $rows): array
    {
        $amounts = MilestonePlan::amounts($rows, $contract->amount_cents);
        $created = [];

        foreach ($rows as $index => $row) {
            $trigger = MilestoneTrigger::from((string) ($row['trigger'] ?? MilestoneTrigger::Date->value));
            $dueOn = $trigger === MilestoneTrigger::Date ? ($row['due_on'] ?? null) : null;

            if ($trigger === MilestoneTrigger::Date && $dueOn === null) {
                throw ValidationException::withMessages([
                    "milestones.$index.due_on" => __('Choose the date this milestone is due.'),
                ]);
            }

            $milestone = Milestone::query()->create([
                'contract_id' => $contract->id,
                'position' => $index + 1,
                'label' => trim((string) $row['label']),
                'amount_cents' => $amounts[$index],
                'due_on' => $dueOn,
                'trigger' => $trigger,
            ]);

            Due::query()->create([
                'kind' => DueKind::Project,
                'direction' => Direction::In,
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'office_id' => $contract->office_id,
                'milestone_id' => $milestone->id,
                'period' => null,
                'due_on' => $dueOn,
                'amount_due_cents' => $milestone->amount_cents,
                'amount_paid_cents' => 0,
                'status' => DueStatus::Pending,
                'description' => $milestone->label,
            ]);

            $created[] = $milestone;
        }

        return $created;
    }
}
