<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('get')
            ->setDescription('Get one credential')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Entry id')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'Filter by service')
            ->addOption('show', null, InputOption::VALUE_NONE, 'Show password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>[placeholder] get not implemented yet</comment>');
        return self::SUCCESS;
    }
}
