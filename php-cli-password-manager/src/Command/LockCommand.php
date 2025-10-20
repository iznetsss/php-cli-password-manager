<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LockCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('lock')
            ->setDescription('Clear secrets in memory and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>[placeholder] lock not implemented yet</comment>');
        return self::SUCCESS;
    }
}
