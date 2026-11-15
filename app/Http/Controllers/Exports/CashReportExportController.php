<?php

namespace App\Http\Controllers\Exports;

use App\Exports\CashReportExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\CashReportQuery;
use App\Support\AlgerianDate;
use App\Support\Dates;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The cash report as an Excel file, with the screen's own filters and the screen's
 * own query. Same permission as the report (the route carries `can:view-reports`).
 */
class CashReportExportController extends Controller
{
    public function __invoke(Request $request, CashReportQuery $report): BinaryFileResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $today = AlgerianDate::today();
        $to = Dates::day($request->string('to')->toString()) ?? $today;
        $from = Dates::day($request->string('from')->toString()) ?? $today->startOfYear();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // A restricted user is held to their own office whatever the URL says.
        $officeId = $actor->office_id ?? ($request->integer('office') ?: null);
        $accountId = $request->integer('account') ?: null;

        return Excel::download(
            new CashReportExport($report->handle($actor, $from, $to, $officeId, $accountId)),
            'khazna-cash-report-'.$from->toDateString().'-'.$to->toDateString().'.xlsx',
        );
    }
}
