<?php

namespace App\Command;

use App\Services\StatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tuneefy:update-stats')]
class StatsUpdaterCommand extends Command
{
    public function __construct(
        private StatsService $statsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->statsService->updateMaterializedViews();

        return Command::SUCCESS;
    }
}
