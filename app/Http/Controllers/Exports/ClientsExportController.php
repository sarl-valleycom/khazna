<?php

namespace App\Http\Controllers\Exports;

use App\Data\ClientFiltersData;
use App\Exports\ClientsExport;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Queries\ClientIndexQuery;
use App\Support\AlgerianDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The clients list as an Excel file, with the screen's filters. Same permission as the list. */
class ClientsExportController extends Controller
{
    public function __invoke(Request $request, ClientIndexQuery $query): BinaryFileResponse
    {
        $this->authorize('viewAny', Client::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Excel::download(
            new ClientsExport($actor, ClientFiltersData::fromRequest($request), $query),
            'khazna-clients-'.AlgerianDate::todayString().'.xlsx',
        );
    }
}
