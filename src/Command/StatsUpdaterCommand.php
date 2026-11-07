<?php

namespace App\Command;

use App\Services\StatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tuneefy:update-stats', description: 'Refreshes the trends, hot items and API stats materialized views')]
class StatsUpdaterCommand extends Command
{
    public function __construct(
        private StatsService $statsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->statsService->updateMaterializedViews();
        } catch (\Throwable $e) {
            // Written to stdout on purpose: cron only appends stdout to the log file
            $output->writeln(sprintf('[%s] Stats update failed: %s', date('c'), $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('[%s] Stats updated', date('c')));

        return Command::SUCCESS;
    }
}
