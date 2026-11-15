<?php

namespace App\Http\Controllers\Exports;

use App\Documents\MonthlyFinanceSummary;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pdf\PdfRenderer;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The month's money on one page. Same permission as the cash report. The salary
 * block is built only for someone who may see salaries.
 */
class MonthlySummaryPdfController extends Controller
{
    public function __invoke(Request $request, MonthlyFinanceSummary $summary, PdfRenderer $pdf): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $month = Dates::month($request->query('month'));

        // A restricted user is held to their own office whatever the URL says.
        $officeId = $actor->office_id ?? ($request->integer('office') ?: null);

        return $pdf->response(
            'pdf.monthly-summary',
            $summary->build($actor, $month, $officeId),
            'Monthly summary',
            'khazna-monthly-summary-'.$month->format('Y-m').'.pdf',
        );
    }
}
