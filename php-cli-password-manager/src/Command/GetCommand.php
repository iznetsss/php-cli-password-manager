<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\ConsolePick;
use App\Support\ConsoleSanitizer;
use App\Support\ConsoleUi;
use App\Support\Secrets;
use App\Support\Audit;
use App\Validation\InputValidator;
use App\Validation\ValidationException;
use App\Vault\VaultAccess;
use App\Vault\VaultRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;

final class GetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('get')
            ->setDescription('Get one credential')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Entry id')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'Filter by service (first match)')
            ->addOption('show', null, InputOption::VALUE_NONE, 'Show password explicitly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();

        $show = (bool)$input->getOption('show');
        $idRaw = (string)($input->getOption('id') ?? '');
        $serviceRaw = (string)($input->getOption('service') ?? '');

        $interactive = ($idRaw === '' && $serviceRaw === '');

        if ($interactive) {
            $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($input, $output, $helper): ?array {
                $state = VaultRepository::state($data);
                $entries = $state['entries'] ?? [];
                if ($entries === []) {
                    $output->writeln('<comment>No entries</comment>');
                    return null;
                }

                $services = ConsolePick::services($entries);
                ConsolePick::printServices($output, $services);
                $selected = ConsolePick::askService($input, $output, $helper, $services);
                if ($selected === null) return null;

                $candidates = VaultRepository::list($state, $selected);
                $entry = $candidates[0] ?? null;
                if ($entry === null) {
                    $output->writeln('<error>Not found</error>');
                    return null;
                }

                ConsoleUi::clear($output);
                $output->writeln('Service: ' . ConsoleSanitizer::safe($entry['service']));
                $output->writeln('Username: ' . ConsoleSanitizer::safe($entry['username']));
                $output->writeln('Created: ' . ConsoleSanitizer::safe($entry['createdAt']));
                $output->writeln('Updated: ' . ConsoleSanitizer::safe($entry['updatedAt']));
                $output->writeln('Note: ' . ConsoleSanitizer::safe($entry['note']));
                $output->writeln('Password: ' . ConsoleSanitizer::safe($entry['password']));

                Audit::log('entry.get', 'success', 0, ['service' => $entry['service']]);
                return null;
            }, reEncryptOnRead: true);

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        if ($idRaw !== '' && (!Uuid::isValid($idRaw) || Uuid::fromString($idRaw)->getVersion() !== 4)) {
            $output->writeln('<error>Invalid id</error>');
            return self::FAILURE;
        }

        $service = '';
        if ($serviceRaw !== '') {
            try {
                $service = InputValidator::service($serviceRaw);
            } catch (ValidationException $e) {
                Audit::log('input.invalid', 'fail', 410, ['code' => $e->codeShort()]);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return self::FAILURE;
            }
        }

        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output, $idRaw, $service, $show): ?array {
            $state = VaultRepository::state($data);

            $entry = null;
            if ($idRaw !== '') {
                $entry = VaultRepository::getById($state, $idRaw);
            } elseif ($service !== '') {
                $candidates = VaultRepository::list($state, $service);
                $entry = $candidates[0] ?? null;
            }

            if ($entry === null) {
                Audit::log('entry.get.fail', 'fail', 404, ['reason' => 'not_found']);
                $output->writeln('<error>Not found</error>');
                return null;
            }

            $output->writeln('ID: ' . ConsoleSanitizer::safe($entry['id']));
            $output->writeln('Service: ' . ConsoleSanitizer::safe($entry['service']));
            $output->writeln('Username: ' . ConsoleSanitizer::safe($entry['username']));
            $output->writeln('Created: ' . ConsoleSanitizer::safe($entry['createdAt']));
            $output->writeln('Updated: ' . ConsoleSanitizer::safe($entry['updatedAt']));
            if (($entry['note'] ?? '') !== '') {
                $output->writeln('Note: [saved]');
            }
            if ($show) {
                $output->writeln('Password: ' . ConsoleSanitizer::safe($entry['password']));
            } else {
                $output->writeln('Password: ' . Secrets::hiddenPlaceholder());
            }

            Audit::log('entry.get', 'success', 0, ['service' => $entry['service']]);
            return null;
        });

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
