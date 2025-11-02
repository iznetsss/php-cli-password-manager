<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\Audit;
use App\Vault\VaultAccess;
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
        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output): ?array {
            $output->writeln('<comment>[placeholder] delete not implemented yet</comment>');
            return null;
        });

        if (!$ok) {
            Audit::log('entry.delete.fail', 'fail', 401, ['reason' => 'access_failed']);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
