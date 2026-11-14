<?php

namespace App\Actions\Contracts;

use App\Data\ContractInput;
use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResolveContractAttributes
{
    /**
     * @return array{client_id: int, office_id: int, amount_cents: int, cycle: string, starts_on: string, ends_on: string|null, auto_renew: bool, notes: string|null}
     */
    public function handle(User $actor, ContractInput $input, ?Contract $contract = null): array
    {
        $client = Client::query()->findOrFail($input->clientId);

        if (! $actor->can('view', $client)) {
            throw ValidationException::withMessages([
                'client_id' => __('Choose a client you can access.'),
            ]);
        }

        if ($contract !== null && $contract->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'client_id' => __('The client of an existing contract cannot be changed.'),
            ]);
        }

        // Only an unrestricted owner or finance user may choose the office. Anyone else
        // gets the contract's current office, or the client's, never a submitted value.
        $chosen = $actor->isAccountManager() ? null : $input->officeId;
        $officeId = $actor->office_id ?? $chosen ?? $contract->office_id ?? $client->office_id;

        if ($officeId === null) {
            throw ValidationException::withMessages([
                'office_id' => __('Choose the office this contract belongs to.'),
            ]);
        }

        $notes = $input->notes !== null ? trim($input->notes) : null;

        return [
            'client_id' => $client->id,
            'office_id' => $officeId,
            'amount_cents' => $input->amountCents,
            'cycle' => $input->cycle,
            'starts_on' => $input->startsOn,
            'ends_on' => $input->endsOn,
            'auto_renew' => $input->autoRenew,
            'notes' => $notes === '' ? null : $notes,
        ];
    }
}
