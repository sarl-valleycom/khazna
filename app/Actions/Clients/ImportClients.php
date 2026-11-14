<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ImportClients
{
    /**
     * @param  list<array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>  $rows
     */
    public function handle(array $rows): int
    {
        return DB::transaction(function () use ($rows): int {
            foreach ($rows as $row) {
                Client::query()->create($row);
            }

            return count($rows);
        });
    }
}
