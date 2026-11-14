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

class LedgerVerifyCommand extends Command
{
    protected $signature = 'ledger:verify';

    protected $description = 'Check that every payment, advance, transfer and manual entry has its ledger entries, and every entry has a source';

    public function handle(LedgerVerifier $verifier): int
    {
        $problems = $verifier->run();

        // The dashboard shows the latest result to the owner.
        Setting::put(Setting::LEDGER_CHECK, json_encode([
            'checked_at' => now()->toIso8601String(),
            'problems' => $problems,
        ], JSON_THROW_ON_ERROR));

        if ($problems === []) {
            $this->info('The ledger is consistent.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        Log::critical('ledger:verify found problems', ['problems' => $problems]);

        $owners = User::query()->where('role', Role::Owner)->where('active', true)->get();
        Notification::send($owners, new LedgerMismatchNotification($problems));

        return self::FAILURE;
    }
}
