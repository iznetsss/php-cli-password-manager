<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Crypto;
use App\Support\Audit;
use App\Support\Secrets;
use App\Vault\VaultAccess;
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
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Password (ignored for safety)')
            ->addOption('note', null, InputOption::VALUE_OPTIONAL, 'Optional note');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = (string)$input->getOption('service');
        $username = (string)$input->getOption('username');

        $cliPassword = $input->getOption('password');
        if (is_string($cliPassword) && $cliPassword !== '') {
            Crypto::zeroize($cliPassword);
            $output->writeln('<comment>CLI password ignored for safety. You will be prompted securely.</comment>');
        }

        $password = Secrets::askHidden($input, $output, 'Password: ');
        $note = (string)($input->getOption('note') ?? '');

        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output, $service, $username, $note): ?array {
            $output->writeln('<comment>[placeholder] add not implemented yet</comment>');
            $output->writeln('Service: ' . $service);
            $output->writeln('Username: ' . $username);
            $output->writeln('Password: ' . Secrets::hiddenPlaceholder());
            if ($note !== '') {
                $output->writeln('Note: [saved]');
            }
            // Return null to skip saving for now
            return null;
        });

        Crypto::zeroize($password);

        if (!$ok) {
            Audit::log('entry.add.fail', 'fail', 401, ['reason' => 'access_failed', 'service' => $service]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
