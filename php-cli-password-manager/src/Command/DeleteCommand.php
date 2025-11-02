<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\Audit;
use App\Support\ConsoleUi;
use App\Support\ConsolePick;
use App\Vault\VaultAccess;
use App\Vault\VaultRepository;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class DeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('delete')
            ->setDescription('Delete credential by id')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Entry id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();
        $idRaw = (string)($input->getOption('id') ?? '');
        $interactive = ($idRaw === '');
        $deleted = null;

        if ($interactive) {
            $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($input, $output, $helper, &$deleted): ?array {
                $state = VaultRepository::state($data);
                $entries = $state['entries'] ?? [];
                if ($entries === []) {
                    $output->writeln('<comment>No entries</comment>');
                    return null;
                }

                $services = ConsolePick::services($entries);
                ConsolePick::printServices($output, $services);
                $sel = ConsolePick::askService($input, $output, $helper, $services);
                if ($sel === null) return null;

                $candidates = VaultRepository::list($state, $sel);
                if ($candidates === []) {
                    $output->writeln('<error>Not found</error>');
                    return null;
                }

                // Pick entry if multiple
                $entry = $candidates[0];
                if (count($candidates) > 1) {
                    $output->writeln('Entries:');
                    foreach ($candidates as $i => $e) {
                        $output->writeln(sprintf('%d) %s [%s]', $i + 1, $e['username'], substr($e['id'], 0, 8)));
                    }
                    $pick = trim((string)$helper->ask($input, $output, new Question('Choose entry number: ')));
                    if (ctype_digit($pick) && $pick !== '0' && isset($candidates[(int)$pick - 1])) {
                        $entry = $candidates[(int)$pick - 1];
                    }
                }

                // Confirm
                $output->writeln('');
                $output->writeln('Service: ' . $entry['service']);
                $output->writeln('Username: ' . $entry['username']);
                $confirm = trim((string)$helper->ask(
                    $input, $output, new Question("Delete this credential?\n1) Yes\n2) No\nSelect number: ")
                ));
                if ($confirm !== '1') {
                    $output->writeln('<comment>Cancelled.</comment>');
                    return null;
                }

                try {
                    $res = VaultRepository::delete($state, (string)$entry['id']);
                } catch (RuntimeException) {
                    $output->writeln('<error>Not found</error>');
                    return null;
                }

                $deleted = $entry;
                return $res['state'];
            });

            if ($ok && $deleted) {
                ConsoleUi::showDeleted($output, $deleted['id'], $deleted['service'], $deleted['username']);
                Audit::log('entry.delete', 'success', 0, ['id' => $deleted['id']]);
                return self::SUCCESS;
            }
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        // Non-interactive mode
        if ($idRaw !== '' && (!Uuid::isValid($idRaw) || Uuid::fromString($idRaw)->getVersion() !== 4)) {
            $output->writeln('<error>Invalid id</error>');
            return self::FAILURE;
        }

        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output, $idRaw, &$deleted): ?array {
            $state = VaultRepository::state($data);
            $entry = VaultRepository::getById($state, $idRaw);
            if ($entry === null) {
                $output->writeln('<error>Not found</error>');
                return null;
            }

            try {
                $res = VaultRepository::delete($state, $idRaw);
            } catch (RuntimeException) {
                $output->writeln('<error>Not found</error>');
                return null;
            }

            $deleted = $entry;
            return $res['state'];
        });

        if ($ok && $deleted) {
            ConsoleUi::showDeleted($output, $deleted['id'], $deleted['service'], $deleted['username']);
            Audit::log('entry.delete', 'success', 0, ['id' => $deleted['id']]);
            return self::SUCCESS;
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
