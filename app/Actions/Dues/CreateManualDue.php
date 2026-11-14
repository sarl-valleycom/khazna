<?php

namespace App\Actions\Dues;

use App\Data\DueInput;
use App\Enums\DueStatus;
use App\Models\Client;
use App\Models\Due;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateManualDue
{
    public function handle(User $actor, Client $client, DueInput $input): Due
    {
        if (! $actor->can('view', $client)) {
            throw ValidationException::withMessages([
                'client_id' => __('Choose a client you can access.'),
            ]);
        }

        $officeId = $actor->office_id ?? $client->office_id;

        if ($officeId === null) {
            throw ValidationException::withMessages([
                'client_id' => __('This client needs an office before a due can be created.'),
            ]);
        }

        $description = $input->description !== null ? trim($input->description) : null;

        return Due::query()->create([
            'kind' => $input->kind,
            'direction' => $input->direction,
            'contract_id' => null,
            'client_id' => $client->id,
            'office_id' => $officeId,
            'period' => $input->dueOn,
            'due_on' => $input->dueOn,
            'amount_due_cents' => $input->amountCents,
            'amount_paid_cents' => 0,
            'status' => DueStatus::Pending,
            'description' => $description === '' ? null : $description,
            'canceled_reason' => null,
        ]);
    }
}
