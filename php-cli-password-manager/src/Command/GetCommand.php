<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\Secrets;
use App\Vault\VaultAccess;
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
            ->addOption('show', null, InputOption::VALUE_NONE, 'Show password explicitly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $show = (bool)$input->getOption('show');

        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output, $show): ?array {
            $output->writeln('<comment>[placeholder] get not implemented yet</comment>');
            $output->writeln('Service: example');
            $output->writeln('Username: user@example.com');
            if ($show) {
                $output->writeln('Password: [will be shown when implemented]');
            } else {
                $output->writeln('Password: ' . Secrets::hiddenPlaceholder());
            }
            return null;
        });

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
