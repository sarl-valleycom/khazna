<?php

namespace App\Http\Controllers\Exports;

use App\Exports\CashReportExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\CashReportQuery;
use App\Support\CashReportFilters;
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

        $filters = CashReportFilters::fromRequest($request, $actor);

        return Excel::download(
            new CashReportExport($report->handle($actor, $filters->from, $filters->to, $filters->officeId, $filters->accountId)),
            'khazna-cash-report-'.$filters->from->toDateString().'-'.$filters->to->toDateString().'.xlsx',
        );
    }
}
