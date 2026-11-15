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
     * The screen's page of clients.
     *
     * @return LengthAwarePaginator<int, Client>
     */
    public function handle(User $user, ClientFiltersData $filters): LengthAwarePaginator
    {
        return $this->query($user, $filters)->paginate($filters->perPage)->withQueryString();
    }

    /**
     * Every client the filters match, in the screen's order. The Excel export
     * walks this same builder, so what is exported is exactly what is shown.
     *
     * @return Builder<Client>
     */
    public function query(User $user, ClientFiltersData $filters): Builder
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

        // The id makes the order unique, so paging and chunked exports never skip or repeat a row.
        return $query->orderBy($filters->sort, $direction)->orderBy('id');
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
