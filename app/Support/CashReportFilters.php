<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The filters of the cash report: a period (default: this year to today, swapped
 * when reversed), an office and an account. The report page and its Excel export
 * both read them here, so a file always holds what the page showed.
 */
final class CashReportFilters
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $officeId,
        public readonly ?int $accountId,
    ) {}

    public static function fromRequest(Request $request, User $actor): self
    {
        $today = AlgerianDate::today();
        $to = Dates::day($request->query('to')) ?? $today;
        $from = Dates::day($request->query('from')) ?? $today->startOfYear();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            $from,
            $to,
            // A restricted user is held to their own office whatever the URL says.
            $actor->office_id ?? ($request->integer('office') ?: null),
            $request->integer('account') ?: null,
        );
    }
}
