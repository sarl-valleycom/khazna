<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ImportClients
{
    /**
     * Insert the previewed rows in one transaction. A phone that has appeared
     * since the preview (another import, a manual client) is skipped rather than
     * duplicated.
     *
     * @param  list<array{name: string, contact: string|null, phone: string, email: string|null, address: string|null, notes: string|null, account_manager_id: int, office_id: int|null}>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function handle(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $existing = Client::query()
                ->whereIn('phone', array_column($rows, 'phone'))
                ->pluck('phone')
                ->all();
            $known = array_fill_keys($existing, true);
            $imported = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                if (isset($known[$row['phone']])) {
                    $skipped++;

                    continue;
                }

                Client::query()->create($row);
                $known[$row['phone']] = true;
                $imported++;
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }
}
