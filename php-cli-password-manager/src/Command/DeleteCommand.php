<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('delete')
            ->setDescription('Delete credential by id')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Entry id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>[placeholder] delete not implemented yet</comment>');
        return self::SUCCESS;
    }
}
