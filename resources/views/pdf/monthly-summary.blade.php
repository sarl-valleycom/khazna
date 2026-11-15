@extends('pdf.layout')

@php
    use App\Support\Money;
    $money = fn (int $cents): string => Money::fromCents($cents)->format();
@endphp

@section('content')
    <h1>Monthly summary</h1>
    <p class="muted"><bdi dir="ltr">{{ $month->format('F Y') }}</bdi> · {{ $office ?? 'All offices' }}</p>

    <h2>Money</h2>
    <table class="grid">
        <tr>
            <td>Money in</td>
            <td class="right"><bdi dir="ltr">{{ $money($inCents) }}</bdi></td>
        </tr>
        <tr>
            <td>Money out</td>
            <td class="right"><bdi dir="ltr">{{ $money($outCents) }}</bdi></td>
        </tr>
        <tr>
            <td class="sumlabel">Cash result (in minus out)</td>
            <td class="sum right"><bdi dir="ltr">{{ $money($resultCents) }}</bdi></td>
        </tr>
    </table>
    <p class="note">Money that actually moved in the month. Transfers between your own accounts are not counted. This is a cash result, not an accounting profit.@if (! $includesPayroll) Payroll is not included in this document.@endif</p>

    @if (count($rows) > 0)
        <h2>By category</h2>
        <table class="grid">
            <tr>
                <th>Category</th>
                <th>Direction</th>
                <th class="right">Amount</th>
            </tr>
            @foreach ($rows as $row)
                <tr>
                    <td><bdi>{{ $row['category'] }}</bdi></td>
                    <td>{{ $row['direction'] === 'in' ? 'In' : 'Out' }}</td>
                    <td class="right"><bdi dir="ltr">{{ $money($row['totalCents']) }}</bdi></td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (count($offices) > 0)
        <h2>By office</h2>
        <table class="grid">
            <tr>
                <th>Office</th>
                <th class="right">In</th>
                <th class="right">Out</th>
                <th class="right">Result</th>
            </tr>
            @foreach ($offices as $row)
                <tr>
                    <td><bdi>{{ $row['officeName'] }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($row['inCents']) }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($row['outCents']) }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($row['resultCents']) }}</bdi></td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>What clients owe</h2>
    <table class="grid">
        <tr>
            <td>Outstanding today</td>
            <td class="right"><bdi dir="ltr">{{ $money($outstandingCents) }}</bdi></td>
        </tr>
        <tr>
            <td>Of which already late</td>
            <td class="right"><bdi dir="ltr">{{ $money($overdueCents) }}</bdi></td>
        </tr>
    </table>
    <p class="note">This is the position on the day the document is made, not at the end of the month.</p>

    @if ($salary !== null)
        <h2>Salaries</h2>
        <table class="grid">
            <tr>
                <td>Base salaries (<bdi dir="ltr">{{ $salary['people'] }}</bdi> people)</td>
                <td class="right"><bdi dir="ltr">{{ $money($salary['baseCents']) }}</bdi></td>
            </tr>
            <tr>
                <td>Bonuses</td>
                <td class="right"><bdi dir="ltr">{{ $money($salary['bonusCents']) }}</bdi></td>
            </tr>
            <tr>
                <td>Deductions</td>
                <td class="right"><bdi dir="ltr">-{{ $money($salary['deductionCents']) }}</bdi></td>
            </tr>
            <tr>
                <td>Advances already given</td>
                <td class="right"><bdi dir="ltr">-{{ $money($salary['advanceCents']) }}</bdi></td>
            </tr>
            <tr>
                <td class="sumlabel">Net salary total</td>
                <td class="sum right"><bdi dir="ltr">{{ $money($salary['netCents']) }}</bdi></td>
            </tr>
        </table>
    @endif

    <p class="note">Generated on <bdi dir="ltr">{{ now()->setTimezone('Africa/Algiers')->format('j M Y, H:i') }}</bdi> (Algiers time).</p>
@endsection
