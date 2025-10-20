<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AddCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('add')
            ->setDescription('Add new credential')
            ->addOption('service', null, InputOption::VALUE_REQUIRED, 'Service name')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Password (will prompt later)')
            ->addOption('note', null, InputOption::VALUE_OPTIONAL, 'Optional note');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>[placeholder] add not implemented yet</comment>');
        return self::SUCCESS;
    }
}
