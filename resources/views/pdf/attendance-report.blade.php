@extends('pdf.layout')

@php
    use App\Attendance\WorkedTime;
    use App\Support\AlgerianDate;
    use Carbon\CarbonImmutable;
@endphp

@section('content')
    <h1>Attendance report</h1>
    <p class="muted"><bdi dir="ltr">{{ $month->format('F Y') }}</bdi></p>

    <div class="box">
        <strong><bdi>{{ $employee->name }}</bdi></strong><br>
        {{ $employee->office->name ?? '' }}
    </div>

    <h2>Summary</h2>
    <table class="grid">
        <tr>
            <td>Hours worked</td>
            <td class="right"><bdi dir="ltr">{{ WorkedTime::format($summary['worked']) }}</bdi></td>
        </tr>
        <tr>
            <td>Hours expected</td>
            <td class="right"><bdi dir="ltr">{{ WorkedTime::format($summary['expected']) }}</bdi></td>
        </tr>
        <tr>
            <td class="sumlabel">To make up</td>
            <td class="sum right"><bdi dir="ltr">{{ WorkedTime::format($summary['to_make_up']) }}</bdi></td>
        </tr>
        @if ($summary['extra'] > 0)
            <tr>
                <td>Extra hours</td>
                <td class="right"><bdi dir="ltr">{{ WorkedTime::format($summary['extra']) }}</bdi></td>
            </tr>
        @endif
        <tr>
            <td>Days worked</td>
            <td class="right"><bdi dir="ltr">{{ $summary['days_worked'] }}</bdi></td>
        </tr>
        <tr>
            <td>Days off (holidays and leave)</td>
            <td class="right"><bdi dir="ltr">{{ $summary['days_off'] }}</bdi></td>
        </tr>
    </table>
    @if ($summary['not_checked'] > 0)
        <p class="note">{{ $summary['not_checked'] }} automatically closed session(s) were not checked, so the hours may be incomplete.</p>
    @endif

    <h2>Days</h2>
    <table class="grid">
        <tr>
            <th>Date</th>
            <th>Day</th>
            <th class="right">Expected</th>
            <th class="right">Worked</th>
            <th>Note</th>
        </tr>
        @foreach ($days as $date => $day)
            <tr>
                <td><bdi dir="ltr">{{ CarbonImmutable::parse($date)->format('j M') }}</bdi></td>
                <td>{{ CarbonImmutable::parse($date)->format('D') }}</td>
                <td class="right"><bdi dir="ltr">{{ $day['expected'] > 0 ? WorkedTime::format($day['expected']) : '-' }}</bdi></td>
                <td class="right"><bdi dir="ltr">{{ $day['worked'] > 0 ? WorkedTime::format($day['worked']) : '-' }}</bdi></td>
                <td>{{ $day['note'] === null ? '' : ucfirst($day['note']) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Sessions</h2>
    @if ($sessions->isEmpty())
        <p class="muted">No sessions this month.</p>
    @else
        <table class="grid">
            <tr>
                <th>Date</th>
                <th>Start</th>
                <th>End</th>
                <th class="right">Worked</th>
                <th>Report</th>
            </tr>
            @foreach ($sessions as $session)
                <tr>
                    <td><bdi dir="ltr">{{ AlgerianDate::toAlgiers($session->started_at)->format('j M') }}</bdi></td>
                    <td><bdi dir="ltr">{{ AlgerianDate::toAlgiers($session->started_at)->format('H:i') }}</bdi></td>
                    <td><bdi dir="ltr">{{ $session->ended_at ? AlgerianDate::toAlgiers($session->ended_at)->format('H:i') : 'open' }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ WorkedTime::format($minutes[$session->id] ?? 0) }}</bdi></td>
                    <td><bdi>{{ $session->report }}</bdi></td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="note">The hours are information. Salary does not depend on them. Generated on <bdi dir="ltr">{{ now()->setTimezone('Africa/Algiers')->format('j M Y, H:i') }}</bdi> (Algiers time).</p>
@endsection
