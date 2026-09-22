<?php

namespace App\Console\Commands;

use App\Services\ClinicalTaskGenerator;
use Illuminate\Console\Command;

class GenerateClinicalTasks extends Command
{
    protected $signature = 'clinical:generate-tasks';

    protected $description = 'Reconcile outstanding clinical tasks from unread results, critical findings, and promised follow-ups';

    public function handle(ClinicalTaskGenerator $generator): int
    {
        $result = $generator->run();

        $this->info("Opened {$result['opened']} task(s), closed {$result['closed']}.");

        return self::SUCCESS;
    }
}
