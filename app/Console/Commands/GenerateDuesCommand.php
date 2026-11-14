<?php

namespace App\Console\Commands;

use App\Actions\Dues\GenerateDues;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDuesCommand extends Command
{
    protected $signature = 'dues:generate';

    protected $description = 'Generate dues for active contracts over the next 30 days';

    public function handle(GenerateDues $generator): int
    {
        $report = $generator->handle();

        Log::info('dues:generate', $report->toArray());

        $this->info(__('Created :created dues for :contracts contracts, skipped :skipped.', [
            'created' => $report->created,
            'contracts' => $report->contractsTouched(),
            'skipped' => $report->skipped,
        ]));

        return self::SUCCESS;
    }
}
