<?php

namespace App\Console\Commands;

use App\Actions\Ledger\LedgerVerifier;
use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LedgerMismatchNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class LedgerVerifyCommand extends Command
{
    /** How many problems are kept for the dashboard. The rest are only counted. */
    public const KEEP = 50;

    protected $signature = 'ledger:verify';

    protected $description = 'Check that every payment, advance, transfer and manual entry has its ledger entries, and every entry has a source';

    public function handle(LedgerVerifier $verifier): int
    {
        $problems = $verifier->run();

        if ($problems !== []) {
            foreach (array_slice($problems, 0, self::KEEP) as $problem) {
                $this->error($problem);
            }

            Log::critical('ledger:verify found problems', ['count' => count($problems), 'first' => array_slice($problems, 0, self::KEEP)]);

            // Alert the owner before anything that could fail: this is the whole point of the check.
            $owners = User::query()->where('role', Role::Owner)->where('active', true)->get();
            Notification::send($owners, new LedgerMismatchNotification(array_slice($problems, 0, self::KEEP), count($problems)));
        }

        try {
            // A capped copy for the dashboard. It changes every night, so it is kept out of the audit log.
            Setting::withoutEvents(fn () => Setting::put(Setting::LEDGER_CHECK, json_encode([
                'checked_at' => now()->toIso8601String(),
                'total' => count($problems),
                'problems' => array_slice($problems, 0, self::KEEP),
            ], JSON_THROW_ON_ERROR)));
        } catch (Throwable $exception) {
            Log::error('ledger:verify could not store its result', ['error' => $exception->getMessage()]);
        }

        if ($problems === []) {
            $this->info('The ledger is consistent.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
