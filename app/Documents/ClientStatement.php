<?php

namespace App\Documents;

use App\Models\Client;
use App\Models\Due;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a client was billed and what they paid in a period, with the totals.
 * Every amount stays in integer centimes until the page prints it.
 *
 * Dues listed are the money-in dues of the client that fall due in the period
 * (not canceled). Payments listed are the ones received in the period and not
 * reversed, whichever due they were for.
 */
class ClientStatement
{
    /**
     * @return array{
     *     client: Client,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     dues: Collection<int, Due>,
     *     payments: Collection<int, Payment>,
     *     dueCents: int,
     *     paidDuesCents: int,
     *     remainingOnDuesCents: int,
     *     receivedCents: int,
     *     totalOutstandingCents: int,
     * }
     */
    public function build(Client $client, User $viewer, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dues = Due::query()
            ->visibleTo($viewer)
            ->where('client_id', $client->id)
            ->where('status', '!=', 'canceled')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '>=', $from->toDateString())
            ->whereDate('due_on', '<=', $to->toDateString())
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->notReversed()
            ->whereHas('due', fn ($due) => $due->where('client_id', $client->id))
            ->whereDate('paid_on', '>=', $from->toDateString())
            ->whereDate('paid_on', '<=', $to->toDateString())
            ->with('due:id,description,period,due_on,kind')
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        $openDues = Due::query()
            ->visibleTo($viewer)
            ->where('client_id', $client->id)
            ->whereIn('status', ['pending', 'partial'])
            ->get(['amount_due_cents', 'amount_paid_cents']);

        return [
            'client' => $client,
            'from' => $from,
            'to' => $to,
            'dues' => $dues,
            'payments' => $payments,
            'dueCents' => (int) $dues->sum('amount_due_cents'),
            'paidDuesCents' => (int) $dues->sum('amount_paid_cents'),
            'remainingOnDuesCents' => (int) $dues->sum(fn (Due $due): int => $due->remainingCents()),
            'receivedCents' => (int) $payments->sum('amount_cents'),
            'totalOutstandingCents' => (int) $openDues->sum(fn (Due $due): int => max(0, $due->amount_due_cents - $due->amount_paid_cents)),
        ];
    }
}
