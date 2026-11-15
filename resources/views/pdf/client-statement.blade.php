@extends('pdf.layout')

@php
    use App\Support\Money;
    $money = fn (int $cents): string => Money::fromCents($cents)->format();
@endphp

@section('content')
    <h1>Client statement</h1>
    <p class="muted">
        <bdi dir="ltr">{{ $from->format('j M Y') }}</bdi> to <bdi dir="ltr">{{ $to->format('j M Y') }}</bdi>
    </p>

    <div class="box">
        <strong><bdi>{{ $client->name }}</bdi></strong><br>
        @if ($client->contact)
            Contact: <bdi>{{ $client->contact }}</bdi><br>
        @endif
        @if ($client->phone)
            Phone: <bdi dir="ltr">{{ \App\Support\Phone::formatLocal($client->phone) ?? $client->phone }}</bdi><br>
        @endif
        @if ($client->email)
            Email: <bdi dir="ltr">{{ $client->email }}</bdi>
        @endif
    </div>

    <h2>Dues falling due in this period</h2>
    @if ($dues->isEmpty())
        <p class="muted">No dues fall due in this period.</p>
    @else
        <table class="grid">
            <tr>
                <th>Due date</th>
                <th>Description</th>
                <th class="right">Amount</th>
                <th class="right">Paid</th>
                <th class="right">Remaining</th>
                <th>Status</th>
            </tr>
            @foreach ($dues as $due)
                <tr>
                    <td><bdi dir="ltr">{{ $due->due_on?->format('j M Y') }}</bdi></td>
                    <td><bdi>{{ $due->description ?: $due->kind->label() }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($due->amount_due_cents) }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($due->amount_paid_cents) }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($due->remainingCents()) }}</bdi></td>
                    <td>{{ $due->isOverdue() ? 'Overdue' : $due->status->label() }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="sumlabel">Total</td>
                <td class="sum"></td>
                <td class="sum right"><bdi dir="ltr">{{ $money($dueCents) }}</bdi></td>
                <td class="sum right"><bdi dir="ltr">{{ $money($paidDuesCents) }}</bdi></td>
                <td class="sum right"><bdi dir="ltr">{{ $money($remainingOnDuesCents) }}</bdi></td>
                <td class="sum"></td>
            </tr>
        </table>
    @endif

    <h2>Payments received in this period</h2>
    @if ($payments->isEmpty())
        <p class="muted">No payments were received in this period.</p>
    @else
        <table class="grid">
            <tr>
                <th>Date</th>
                <th>For</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="right">Amount</th>
            </tr>
            @foreach ($payments as $payment)
                <tr>
                    <td><bdi dir="ltr">{{ $payment->paid_on->format('j M Y') }}</bdi></td>
                    <td><bdi>{{ $payment->due?->description ?: $payment->due?->kind->label() }}</bdi></td>
                    <td>{{ $payment->method->label() }}</td>
                    <td>@if ($payment->reference)<bdi dir="ltr">{{ $payment->reference }}</bdi>@endif</td>
                    <td class="right"><bdi dir="ltr">{{ $money($payment->amount_cents) }}</bdi></td>
                </tr>
            @endforeach
            <tr>
                <td class="sumlabel">Total received</td>
                <td class="sum"></td>
                <td class="sum"></td>
                <td class="sum"></td>
                <td class="sum right"><bdi dir="ltr">{{ $money($receivedCents) }}</bdi></td>
            </tr>
        </table>
    @endif

    <h2>Balance</h2>
    <table class="grid">
        <tr>
            <td>Still to pay on the dues listed above</td>
            <td class="right"><bdi dir="ltr">{{ $money($remainingOnDuesCents) }}</bdi></td>
        </tr>
        <tr>
            <td class="sumlabel">Total outstanding for this client, all periods</td>
            <td class="sum right"><bdi dir="ltr">{{ $money($totalOutstandingCents) }}</bdi></td>
        </tr>
    </table>

    <p class="note">Generated on <bdi dir="ltr">{{ now()->setTimezone('Africa/Algiers')->format('j M Y, H:i') }}</bdi> (Algiers time). Reversed payments and canceled dues are not shown.</p>
@endsection
