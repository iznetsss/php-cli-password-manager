<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list')
            ->setDescription('List credentials (no passwords)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>[placeholder] list not implemented yet</comment>');
        return self::SUCCESS;
    }
}
