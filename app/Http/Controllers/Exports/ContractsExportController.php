<?php

namespace App\Http\Controllers\Exports;

use App\Data\ContractFiltersData;
use App\Exports\ContractsExport;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\User;
use App\Queries\ContractIndexQuery;
use App\Support\AlgerianDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The contracts list as an Excel file, with the screen's filters. Same permission as the list. */
class ContractsExportController extends Controller
{
    public function __invoke(Request $request, ContractIndexQuery $query): BinaryFileResponse
    {
        $this->authorize('viewAny', Contract::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return Excel::download(
            new ContractsExport($actor, ContractFiltersData::fromRequest($request), $query),
            'khazna-contracts-'.AlgerianDate::todayString().'.xlsx',
        );
    }
}
