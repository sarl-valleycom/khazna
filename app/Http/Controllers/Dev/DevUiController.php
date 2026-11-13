<?php

namespace App\Http\Controllers\Dev;

use App\Data\AccountOptionData;
use App\Data\DemoDueRowData;
use App\Data\DevUiData;
use App\Data\ForecastPointData;
use App\Data\OfficeOptionData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DevUiController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(app()->environment(['local', 'staging']), 404);

        $money = fn (int $cents): string => number_format($cents / 100, 2, '.', ',').' DA';

        $offices = [
            new OfficeOptionData(1, 'Algiers HQ', 'Algiers'),
            new OfficeOptionData(2, 'Oran Office', 'Oran'),
        ];

        $accounts = [
            new AccountOptionData(1, 1, 'Algiers HQ', 'Cash A', 'cash', 1250000, $money(1250000)),
            new AccountOptionData(2, 1, 'Algiers HQ', 'CCP A', 'ccp', 8450050, $money(8450050)),
            new AccountOptionData(3, 2, 'Oran Office', 'Cash B', 'cash', 3200000, $money(3200000)),
        ];

        $rows = [
            new DemoDueRowData(1, 'SARL El Nour', 'شركة النور', '0550 12 34 56', '2026-11-15', 'pending', 'in', 10000000, $money(10000000)),
            new DemoDueRowData(2, 'Amine Bensalem', 'أمين بن سالم', '0661 23 45 67', '2026-11-10', 'partial', 'in', 4500000, $money(4500000)),
            new DemoDueRowData(3, 'Oran Digital', 'أوران ديجيتال', '0770 98 76 54', '2026-11-05', 'paid', 'in', 3000000, $money(3000000)),
            new DemoDueRowData(4, 'Hosting renewal', 'تجديد الاستضافة', '0555 44 33 22', '2026-11-20', 'pending', 'out', 1200000, $money(1200000)),
        ];

        $forecast = [];
        $balance = 12900050;
        for ($week = 0; $week < 13; $week++) {
            $inflow = 2500000 + ($week % 4) * 750000;
            $outflow = 1800000 + ($week % 3) * 400000;
            $balance += $inflow - $outflow;
            $forecast[] = new ForecastPointData(
                label: 'W'.($week + 1),
                inflow: $inflow,
                outflow: $outflow,
                balance: $balance,
            );
        }

        return Inertia::render('dev/Ui', [
            'page' => new DevUiData(
                title: 'Shared UI kit',
                clientNameArabic: 'شركة النور',
                amountCents: 10000050,
                amountFormatted: $money(10000050),
                date: '2026-11-13',
                offices: $offices,
                accounts: $accounts,
                rows: $rows,
                forecast: $forecast,
                statuses: [
                    'pending' => 'Pending',
                    'partial' => 'Partial',
                    'paid' => 'Paid',
                    'canceled' => 'Canceled',
                ],
            ),
            'generatedAt' => Inertia::defer(fn (): string => now()->toIso8601String()),
        ]);
    }
}
