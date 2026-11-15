@extends('pdf.layout')

@php
    use App\Enums\Direction;
    use App\Support\Money;
    $money = fn (int $cents): string => Money::fromCents($cents)->formatWithoutCurrency();
    $period = match (true) {
        $from !== null && $to !== null => \Carbon\CarbonImmutable::parse($from)->format('j M Y').' to '.\Carbon\CarbonImmutable::parse($to)->format('j M Y'),
        $from !== null => 'From '.\Carbon\CarbonImmutable::parse($from)->format('j M Y'),
        $to !== null => 'Up to '.\Carbon\CarbonImmutable::parse($to)->format('j M Y'),
        default => 'All time',
    };
@endphp

@section('content')
    <h1>Account statement</h1>
    <p class="muted"><bdi dir="ltr">{{ $period }}</bdi></p>

    <div class="box">
        <strong><bdi>{{ $account->name }}</bdi></strong><br>
        {{ $account->office->name ?? '' }} · {{ $account->type->label() }}
    </div>

    <h2>Summary</h2>
    <table class="grid">
        <tr>
            <td>Opening balance</td>
            <td class="right"><bdi dir="ltr">{{ $money($summary['startCents']) }} DA</bdi></td>
        </tr>
        <tr>
            <td>Money in</td>
            <td class="right"><bdi dir="ltr">{{ $money($summary['totalInCents']) }} DA</bdi></td>
        </tr>
        <tr>
            <td>Money out</td>
            <td class="right"><bdi dir="ltr">-{{ $money($summary['totalOutCents']) }} DA</bdi></td>
        </tr>
        <tr>
            <td class="sumlabel">Closing balance</td>
            <td class="sum right"><bdi dir="ltr">{{ $money($summary['endCents']) }} DA</bdi></td>
        </tr>
    </table>

    <h2>Movements</h2>
    @if ($entries->isEmpty())
        <p class="muted">No movements in this period.</p>
    @else
        <table class="grid">
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th class="right">In</th>
                <th class="right">Out</th>
                <th class="right">Balance</th>
            </tr>
            @foreach ($entries as $entry)
                <tr>
                    <td><bdi dir="ltr">{{ $entry->entry_date->format('j M Y') }}</bdi></td>
                    <td><bdi>{{ $entry->description }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $entry->direction === Direction::In ? $money($entry->amount_cents) : '' }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $entry->direction === Direction::Out ? $money($entry->amount_cents) : '' }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money((int) $entry->running_cents) }}</bdi></td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="note">Amounts in DA. Generated on <bdi dir="ltr">{{ now()->setTimezone('Africa/Algiers')->format('j M Y, H:i') }}</bdi> (Algiers time).@if (! auth()->user()?->can('view-salaries')) Payroll movements are shown as one line per day.@endif</p>
@endsection
