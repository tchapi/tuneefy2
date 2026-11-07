<?php

namespace App\Command;

use App\Repository\ItemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tuneefy:clean-expired-intents', description: 'Deletes the intents that have expired')]
class ExpiredIntentsCleanerCommand extends Command
{
    public function __construct(
        private ItemRepository $itemRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $deleted = $this->itemRepository->cleanExpiredIntents();
        } catch (\Throwable $e) {
            // Written to stdout on purpose: cron only appends stdout to the log file
            $output->writeln(sprintf('[%s] Intents cleanup failed: %s', date('c'), $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('[%s] %d expired intent(s) deleted', date('c'), $deleted));

        return Command::SUCCESS;
    }
}
