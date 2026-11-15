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
     * The screen's page of contracts.
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function handle(User $user, ContractFiltersData $filters): LengthAwarePaginator
    {
        return $this->query($user, $filters)->paginate($filters->perPage)->withQueryString();
    }

    /**
     * Every contract the filters match, in the screen's order. The Excel export
     * walks this same builder.
     *
     * @return Builder<Contract>
     */
    public function query(User $user, ContractFiltersData $filters): Builder
    {
        $query = Contract::query()
            ->visibleTo($user)
            ->withParties();

        if ($filters->q !== '') {
            $term = '%'.addcslashes($filters->q, '\\%_').'%';

            $query->where(fn (Builder $query): Builder => $query
                ->whereHas('client', fn (Builder $client): Builder => $client->where('name', 'like', $term))
                ->orWhereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('name', 'like', $term)));
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

        // The id makes the order unique, so paging and chunked exports never skip or repeat a row.
        return $query->orderBy($filters->sort, $direction)->orderBy('id');
    }
}
