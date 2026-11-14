<?php

namespace App\Queries;

use App\Data\ClientFiltersData;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function handle(User $user, ClientFiltersData $filters): LengthAwarePaginator
    {
        $query = Client::query()
            ->visibleTo($user)
            ->with(['accountManager:id,name,email,office_id', 'office:id,name']);

        if ($filters->q !== '') {
            $this->search($query, $filters->q);
        }

        if ($filters->accountManager !== null && ! $user->isAccountManager()) {
            $query->where('account_manager_id', $filters->accountManager);
        }

        if ($filters->office !== null) {
            $query->where('office_id', $filters->office);
        }

        $direction = $filters->direction === 'desc' ? 'desc' : 'asc';
        $query->orderBy($filters->sort, $direction);

        return $query->paginate($filters->perPage)->withQueryString();
    }

    /**
     * @param  Builder<Client>  $query
     */
    private function search(Builder $query, string $search): void
    {
        $term = '%'.addcslashes($search, '\\%_').'%';
        $digits = preg_replace('/\D/', '', $search) ?? '';
        $digits = ltrim($digits, '0');

        $query->where(function (Builder $query) use ($term, $digits): void {
            $query->where('name', 'like', $term);

            if ($digits !== '') {
                $query->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }
}
