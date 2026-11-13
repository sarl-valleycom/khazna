<?php

namespace App\Http\Controllers\Dev;

use App\Data\AccountOptionData;
use App\Data\DemoDueRowData;
use App\Data\DevUiData;
use App\Data\ForecastPointData;
use App\Data\OfficeOptionData;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DevUiController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(app()->environment(['local', 'staging']), 404);

        $money = fn (int $cents): string => Money::fromCents($cents)->format();

        $offices = [
            new OfficeOptionData(1, 'Algiers HQ', 'Algiers'),
            new OfficeOptionData(2, 'Oran Office', 'Oran'),
        ];

        $accounts = [
            new AccountOptionData(1, 1, 'Algiers HQ', 'Cash A', 'cash', 1250000, $money(1250000)),
            new AccountOptionData(2, 1, 'Algiers HQ', 'CCP A', 'ccp', 8450050, $money(8450050)),
            new AccountOptionData(3, 2, 'Oran Office', 'Cash B', 'cash', 3200000, $money(3200000)),
        ];

        $allRows = collect([
            new DemoDueRowData(1, 'SARL El Nour', 'شركة النور', '0550 12 34 56', '2026-11-15', 'pending', 'in', 10000000, $money(10000000)),
            new DemoDueRowData(2, 'Amine Bensalem', 'أمين بن سالم', '0661 23 45 67', '2026-11-10', 'partial', 'in', 4500000, $money(4500000)),
            new DemoDueRowData(3, 'Oran Digital', 'أوران ديجيتال', '0770 98 76 54', '2026-11-05', 'paid', 'in', 3000000, $money(3000000)),
            new DemoDueRowData(4, 'Hosting renewal', 'تجديد الاستضافة', '0555 44 33 22', '2026-11-20', 'pending', 'out', 1200000, $money(1200000)),
            new DemoDueRowData(5, 'SARL Horizon', 'شركة الأفق', '0551 11 22 33', '2026-10-28', 'paid', 'in', 7800000, $money(7800000)),
            new DemoDueRowData(6, 'Office rent', 'إيجار المكتب', '0552 33 44 55', '2026-11-01', 'paid', 'out', 4500000, $money(4500000)),
            new DemoDueRowData(7, 'Nadia Cherif', 'نادية شريف', '0662 44 55 66', '2026-11-25', 'pending', 'in', 2300000, $money(2300000)),
            new DemoDueRowData(8, 'Internet bill', 'فاتورة الإنترنت', '0553 55 66 77', '2026-11-18', 'pending', 'out', 650000, $money(650000)),
        ]);

        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $status = is_string($status) && $status !== '' ? $status : null;
        $tab = (string) $request->query('tab', 'all');
        $office = $request->query('office');
        $office = is_string($office) && $office !== '' ? $office : null;
        $from = $request->query('from');
        $from = is_string($from) && $from !== '' ? $from : null;
        $to = $request->query('to');
        $to = is_string($to) && $to !== '' ? $to : null;
        $sort = $request->query('sort');
        $sort = is_string($sort) && $sort !== '' ? $sort : 'dueOn';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $currentPage = max(1, $request->integer('page', 1));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $tabCounts = [
            'all' => $allRows->count(),
            'in' => $allRows->where('direction', 'in')->count(),
            'out' => $allRows->where('direction', 'out')->count(),
            'paid' => $allRows->where('status', 'paid')->count(),
        ];

        $filtered = $allRows
            ->when($search !== '', fn (Collection $rows): Collection => $rows
                ->filter(fn (DemoDueRowData $row): bool => str_contains(
                    mb_strtolower($row->client.' '.$row->clientArabic.' '.$row->phone),
                    mb_strtolower($search),
                )))
            ->when($status !== null, fn (Collection $rows): Collection => $rows->where('status', $status))
            ->when($tab !== 'all', fn (Collection $rows): Collection => $tab === 'paid'
                ? $rows->where('status', 'paid')
                : $rows->where('direction', $tab))
            ->when($from !== null, fn (Collection $rows): Collection => $rows->where('dueOn', '>=', $from))
            ->when($to !== null, fn (Collection $rows): Collection => $rows->where('dueOn', '<=', $to));

        $filtered = $sort === 'amountCents'
            ? $filtered->sortBy('amountCents', SORT_REGULAR, $direction === 'desc')
            : $filtered->sortBy($sort === 'client' ? 'client' : 'dueOn', SORT_REGULAR, $direction === 'desc');

        $total = $filtered->count();
        $rows = $filtered
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values()
            ->all();

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
                tabCounts: $tabCounts,
                total: $total,
                currentPage: $currentPage,
                perPage: $perPage,
                sort: $sort,
                direction: $direction,
                search: $search,
                statusFilter: $status,
                activeTab: $tab,
                officeFilter: $office,
                from: $from,
                to: $to,
            ),
            'generatedAt' => Inertia::defer(fn (): string => now()->toIso8601String()),
        ]);
    }
}
