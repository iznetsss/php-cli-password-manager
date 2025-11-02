<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\Audit;
use App\Vault\VaultAccess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Update credential fields')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Entry id')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'New service')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'New username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'New password')
            ->addOption('note', null, InputOption::VALUE_OPTIONAL, 'New note');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output): ?array {
            $output->writeln('<comment>[placeholder] update not implemented yet</comment>');
            return null;
        });

        if (!$ok) {
            Audit::log('entry.update.fail', 'fail', 401, ['reason' => 'access_failed']);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
