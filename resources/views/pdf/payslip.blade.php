@extends('pdf.layout')

@php
    use App\Attendance\WorkedTime;
    use App\Support\Money;
    $money = fn (int $cents): string => Money::fromCents($cents)->format();
@endphp

@section('content')
    <h1>Payslip</h1>
    <p class="muted"><bdi dir="ltr">{{ $month->format('F Y') }}</bdi></p>

    <div class="box">
        <strong><bdi>{{ $employee->name }}</bdi></strong><br>
        @if ($employee->title)
            <bdi>{{ $employee->title }}</bdi><br>
        @endif
        {{ $employee->office->name ?? '' }}
    </div>

    <h2>Pay</h2>
    <table class="grid">
        <tr>
            <td>Salary</td>
            <td class="right"><bdi dir="ltr">{{ $money($figures['salary']) }}</bdi></td>
        </tr>
        <tr>
            <td>Bonuses</td>
            <td class="right"><bdi dir="ltr">{{ $money($figures['bonuses']) }}</bdi></td>
        </tr>
        <tr>
            <td>Deductions</td>
            <td class="right"><bdi dir="ltr">-{{ $money($figures['deductions']) }}</bdi></td>
        </tr>
        <tr>
            <td>Advances already given</td>
            <td class="right"><bdi dir="ltr">-{{ $money($figures['advances']) }}</bdi></td>
        </tr>
        <tr>
            <td class="sumlabel">Net to pay</td>
            <td class="sum right"><bdi dir="ltr">{{ $money($figures['net']) }}</bdi></td>
        </tr>
    </table>

    @if ($adjustments->isNotEmpty())
        <h2>Adjustments</h2>
        <table class="grid">
            <tr>
                <th>Type</th>
                <th>Reason</th>
                <th class="right">Amount</th>
            </tr>
            @foreach ($adjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->type->label() }}</td>
                    <td><bdi>{{ $adjustment->reason }}</bdi></td>
                    <td class="right"><bdi dir="ltr">{{ $money($adjustment->amount_cents) }}</bdi></td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Attendance</h2>
    <table class="grid">
        <tr>
            <td>Hours worked</td>
            <td class="right"><bdi dir="ltr">{{ WorkedTime::format($attendance['worked']) }}</bdi></td>
        </tr>
        <tr>
            <td>Hours expected</td>
            <td class="right"><bdi dir="ltr">{{ WorkedTime::format($attendance['expected']) }}</bdi></td>
        </tr>
        @if ($attendance['to_make_up'] > 0)
            <tr>
                <td>Hours to make up</td>
                <td class="right"><bdi dir="ltr">{{ WorkedTime::format($attendance['to_make_up']) }}</bdi></td>
            </tr>
        @endif
        @if ($attendance['extra'] > 0)
            <tr>
                <td>Extra hours</td>
                <td class="right"><bdi dir="ltr">{{ WorkedTime::format($attendance['extra']) }}</bdi></td>
            </tr>
        @endif
        <tr>
            <td>Days worked</td>
            <td class="right"><bdi dir="ltr">{{ $attendance['days_worked'] }}</bdi></td>
        </tr>
        <tr>
            <td>Days off (holidays and leave)</td>
            <td class="right"><bdi dir="ltr">{{ $attendance['days_off'] }}</bdi></td>
        </tr>
    </table>
    <p class="note">The hours are shown for information. The salary does not depend on them: any deduction is listed above.@if ($attendance['not_checked'] > 0) {{ $attendance['not_checked'] }} automatically closed session(s) were not checked, so the hours may be incomplete.@endif</p>

    <p class="note">Generated on <bdi dir="ltr">{{ now()->setTimezone('Africa/Algiers')->format('j M Y, H:i') }}</bdi> (Algiers time).</p>
@endsection
