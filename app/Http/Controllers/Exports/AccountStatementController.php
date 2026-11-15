<?php

namespace App\Http\Controllers\Exports;

use App\Exports\AccountStatementExport;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Pdf\PdfRenderer;
use App\Queries\AccountStatementQuery;
use App\Support\AlgerianDate;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * An account's statement for a period, as Excel or PDF. The period is the same
 * optional from and to as the account page, and so is the permission: the same
 * query feeds all three, so payroll is masked the same way.
 */
class AccountStatementController extends Controller
{
    /** A PDF of thousands of lines is unreadable and heavy; Excel has no such limit. */
    private const PDF_MAX_LINES = 2000;

    public function pdf(Request $request, Account $account, AccountStatementQuery $statement, PdfRenderer $pdf): Response
    {
        [$actor, $from, $to] = $this->context($request, $account);

        abort_if(
            $statement->count($account, $from, $to) > self::PDF_MAX_LINES,
            422,
            __('This period has more than :max lines. Choose a shorter period, or export to Excel.', ['max' => self::PDF_MAX_LINES]),
        );

        $summary = $statement->summary($account, $from, $to);
        $account->loadMissing('office:id,name');

        return $pdf->response(
            'pdf.account-statement',
            [
                'account' => $account,
                'from' => $from,
                'to' => $to,
                'summary' => $summary,
                'entries' => $statement->entries($account, $actor, $from, $to, $summary['startCents'])->get(),
            ],
            'Account statement',
            'khazna-account-'.PdfRenderer::slug($account->name, 'account-'.$account->id).'-'.($from ?? 'start').'-'.($to ?? AlgerianDate::todayString()).'.pdf',
        );
    }

    public function excel(Request $request, Account $account, AccountStatementQuery $statement): BinaryFileResponse
    {
        [$actor, $from, $to] = $this->context($request, $account);
        $account->loadMissing('office:id,name');

        return Excel::download(
            new AccountStatementExport($account, $actor, $from, $to, $statement),
            'khazna-account-'.PdfRenderer::slug($account->name, 'account-'.$account->id).'-'.($from ?? 'start').'-'.($to ?? AlgerianDate::todayString()).'.xlsx',
        );
    }

    /**
     * @return array{0: User, 1: string|null, 2: string|null}
     */
    private function context(Request $request, Account $account): array
    {
        $this->authorize('view', $account);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return [$actor, $filters['from'] ?? null, $filters['to'] ?? null];
    }

    public static function money(int $cents): string
    {
        return Money::fromCents($cents)->format();
    }
}
