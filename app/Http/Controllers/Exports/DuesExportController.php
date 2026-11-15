<?php

namespace App\Http\Controllers\Exports;

use App\Data\TriageFiltersData;
use App\Exports\DuesExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\DueTriageQuery;
use App\Support\AlgerianDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The dues of a triage tab as an Excel file, with the screen's filters. The
 * viewer's visibility is part of the query: nobody exports what the screen hides.
 * The "needs attention" tab lists contracts, not dues, so it has no export.
 */
class DuesExportController extends Controller
{
    public function __invoke(Request $request, DueTriageQuery $triage): BinaryFileResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $filters = TriageFiltersData::fromRequest($request);

        abort_if($filters->tab === 'attention', 404);

        $today = AlgerianDate::today();

        return Excel::download(
            new DuesExport($actor, $filters, $today, $triage),
            'khazna-dues-'.$filters->tab.'-'.$today->toDateString().'.xlsx',
        );
    }
}
