<?php

namespace App\Http\Controllers\Exports;

use App\Documents\ClientStatement;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Pdf\PdfRenderer;
use App\Support\AlgerianDate;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A client's statement as a PDF for a period (default: this month). Same
 * permission as viewing the client.
 */
class ClientStatementController extends Controller
{
    public function __invoke(Request $request, Client $client, ClientStatement $statement, PdfRenderer $pdf): Response
    {
        $this->authorize('view', $client);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $today = AlgerianDate::today();
        $from = Dates::day($request->string('from')->toString()) ?? $today->startOfMonth();
        $to = Dates::day($request->string('to')->toString()) ?? $today->endOfMonth()->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // A statement is for a period, not for ever: cap it so one request stays small.
        abort_if($from->diffInDays($to) > 366 * 5, 422, __('Choose a period of at most five years.'));

        return $pdf->response(
            'pdf.client-statement',
            $statement->build($client, $actor, $from, $to),
            'Client statement',
            'khazna-statement-'.PdfRenderer::slug($client->name, 'client-'.$client->id).'-'.$from->toDateString().'-'.$to->toDateString().'.pdf',
        );
    }
}
