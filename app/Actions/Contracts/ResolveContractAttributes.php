<?php

namespace App\Actions\Contracts;

use App\Data\ContractInput;
use App\Enums\ContractType;
use App\Enums\Direction;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ResolveContractAttributes
{
    /**
     * @return array{type: ContractType, direction: Direction, client_id: int|null, supplier_id: int|null, category_id: int|null, office_id: int, amount_cents: int, cycle: string, starts_on: string, ends_on: string|null, auto_renew: bool, notes: string|null}
     */
    public function handle(User $actor, ContractInput $input, ?Contract $contract = null): array
    {
        if ($contract !== null && $contract->type !== $input->type) {
            throw ValidationException::withMessages([
                'type' => __('The type of an existing contract cannot be changed.'),
            ]);
        }

        $isCost = $input->type === ContractType::RecurringCost;

        // A project's total is fixed by its milestones; changing it means rebalancing them.
        if ($contract !== null && $input->type === ContractType::CustomProject && $input->amountCents !== $contract->amount_cents) {
            throw ValidationException::withMessages([
                'amount_cents' => __('The amount of a project changes by rebalancing its milestones.'),
            ]);
        }

        // Costs are the company's own business: not for account managers, who only deal with clients.
        if ($isCost && $actor->isAccountManager()) {
            throw new AuthorizationException(__('You are not allowed to do that.'));
        }

        $client = null;

        if ($isCost) {
            $supplier = Supplier::query()->findOrFail($input->supplierId);

            if ($contract !== null && $contract->supplier_id !== $supplier->id) {
                throw ValidationException::withMessages([
                    'supplier_id' => __('The supplier of an existing contract cannot be changed.'),
                ]);
            }
        } else {
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
        }

        // Only an unrestricted owner or finance user may choose the office. Anyone else
        // gets the contract's current office, or the client's, never a submitted value.
        $chosen = $actor->isAccountManager() ? null : $input->officeId;
        $officeId = $actor->office_id ?? $chosen ?? $contract->office_id ?? $client->office_id ?? null;

        if ($officeId === null) {
            throw ValidationException::withMessages([
                'office_id' => __('Choose the office this contract belongs to.'),
            ]);
        }

        $notes = $input->notes !== null ? trim($input->notes) : null;

        return [
            'type' => $input->type,
            'direction' => $isCost ? Direction::Out : Direction::In,
            'client_id' => $client?->id,
            'supplier_id' => $isCost ? $input->supplierId : null,
            'category_id' => $isCost ? $input->categoryId : null,
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
