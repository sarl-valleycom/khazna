<?php

namespace App\Queries;

use App\Data\PaymentAccountData;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The accounts a user may pick when recording a payment, plus, for each client,
 * the account that client last paid into (so the form can preselect it).
 */
class PaymentAccountOptionsQuery
{
    /**
     * @param  list<int>  $clientIds
     * @return array{accounts: array<int, PaymentAccountData>, defaults: array<int, int>}
     */
    public function handle(User $actor, array $clientIds): array
    {
        $accounts = Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->with('office:id,name')
            ->orderBy('office_id')
            ->orderBy('name')
            ->get();

        $defaults = [];

        if ($clientIds !== [] && $accounts->isNotEmpty()) {
            $latest = DB::table('payments')
                ->join('dues', 'dues.id', '=', 'payments.due_id')
                ->whereIn('dues.client_id', $clientIds)
                ->whereIn('payments.account_id', $accounts->modelKeys())
                ->whereNull('payments.deleted_at')
                ->select('dues.client_id', 'payments.account_id', DB::raw('MAX(payments.id) AS last_payment_id'))
                ->groupBy('dues.client_id', 'payments.account_id')
                ->get()
                ->sortBy('last_payment_id');

            // Sorted ascending, so the most recent account for each client wins.
            foreach ($latest as $row) {
                $defaults[(int) $row->client_id] = (int) $row->account_id;
            }
        }

        return [
            'accounts' => $accounts
                ->map(fn (Account $account): PaymentAccountData => PaymentAccountData::fromAccount($account))
                ->values()
                ->all(),
            'defaults' => $defaults,
        ];
    }
}
