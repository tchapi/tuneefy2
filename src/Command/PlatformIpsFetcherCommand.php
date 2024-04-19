<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tuneefy:fetch-platform-ips')]
class PlatformIpsFetcherCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hosts = [
            'listen.tidal.com',
            // 'www.amazon.com',
            'api.deezer.com',
            // 'www.googleapis.com',
            'itunes.apple.com',
            'ws.audioscrobbler.com',
            'api.mixcloud.com',
            'api.napster.com',
            'www.qobuz.com',
            'api.soundcloud.com',
        ];

        echo "[\n";
        foreach ($hosts as $id => $value) {
            $ips = gethostbynamel($value);
            echo "  '".$value.':443:'.$ips[0]."',\n";
        }
        echo "];\n";

        return Command::SUCCESS;
    }
}
