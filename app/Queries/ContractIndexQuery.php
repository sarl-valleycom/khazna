<?php

namespace App\Queries;

use App\Data\ContractFiltersData;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ContractIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Contract>
     */
    public function handle(User $user, ContractFiltersData $filters): LengthAwarePaginator
    {
        $query = Contract::query()
            ->visibleTo($user)
            ->with(['client:id,name,account_manager_id', 'office:id,name']);

        if ($filters->q !== '') {
            $term = '%'.addcslashes($filters->q, '\\%_').'%';

            $query->whereHas('client', fn (Builder $query): Builder => $query->where('name', 'like', $term));
        }

        if ($filters->status !== '') {
            $query->where('status', $filters->status);
        }

        if ($filters->client !== null) {
            $query->where('client_id', $filters->client);
        }

        if ($filters->office !== null) {
            $query->where('office_id', $filters->office);
        }

        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';
        $query->orderBy($filters->sort, $direction);

        return $query->paginate($filters->perPage)->withQueryString();
    }
}
