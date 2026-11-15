<?php

namespace App\Http\Controllers\Exports;

use App\Documents\MonthlyFinanceSummary;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pdf\PdfRenderer;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
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

        $current = AlgerianDate::today()->startOfMonth();
        $month = $this->month($request->query('month'), $current);

        // A restricted user is held to their own office whatever the URL says.
        $officeId = $actor->office_id ?? ($request->integer('office') ?: null);

        return $pdf->response(
            'pdf.monthly-summary',
            $summary->build($actor, $month, $officeId),
            'Monthly summary',
            'khazna-monthly-summary-'.$month->format('Y-m').'.pdf',
        );
    }

    /** A month as Y-m, never later than the current one. */
    private function month(mixed $value, CarbonImmutable $current): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $current;
        }

        $month = CarbonImmutable::parse($value.'-01', AlgerianDate::TZ)->startOfMonth();

        return $month->gt($current) ? $current : $month;
    }
}
