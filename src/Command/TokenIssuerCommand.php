<?php

namespace App\Command;

use App\Services\ClientCredentialsGrant;
use League\OAuth2\Server\AuthorizationServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Issues a (long-lived) access token for an API client, e.g. for clients
 * that keep a static token in their configuration.
 */
#[AsCommand(
    name: 'tuneefy:issue-token',
    description: 'Issues an access token for an API client, with a custom lifetime',
)]
class TokenIssuerCommand extends Command
{
    public function __construct(
        private AuthorizationServer $server,
        private ClientCredentialsGrant $grant,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('client', InputArgument::REQUIRED, 'The client identifier')
            ->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Lifetime of the token, as a DateInterval spec', 'P20Y')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $ttl = new \DateInterval($input->getOption('ttl'));
        } catch (\Exception) {
            $io->error(sprintf('"%s" is not a valid lifetime (expected a DateInterval spec such as P20Y, P30D or PT1H).', $input->getOption('ttl')));

            return Command::INVALID;
        }

        $this->server->enableGrantType($this->grant);
        $token = $this->grant->getAccessTokenForClient($ttl, $input->getArgument('client'));

        if (null === $token) {
            $io->error(sprintf('No client with identifier "%s".', $input->getArgument('client')));

            return Command::FAILURE;
        }

        $io->text(sprintf('Token for <info>%s</info>, expires on <info>%s</info>:', $input->getArgument('client'), $token->getExpiryDateTime()->format('Y-m-d H:i:s')));
        $io->newLine();
        $output->writeln($token->toString());

        return Command::SUCCESS;
    }
}
