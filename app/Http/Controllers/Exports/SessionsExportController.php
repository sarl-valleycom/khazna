<?php

namespace App\Http\Controllers\Exports;

use App\Data\AttendanceFiltersData;
use App\Exports\SessionsExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Queries\SessionIndexQuery;
use App\Support\AlgerianDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The sessions list as an Excel file, with the screen's filters. Owner only, like the list. */
class SessionsExportController extends Controller
{
    public function __invoke(Request $request, SessionIndexQuery $query): BinaryFileResponse
    {
        $this->authorize('viewAny', AttendanceSession::class);

        return Excel::download(
            new SessionsExport(AttendanceFiltersData::fromRequest($request), $query),
            'khazna-sessions-'.AlgerianDate::todayString().'.xlsx',
        );
    }
}
