<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Crypto;
use App\Support\Audit;
use App\Support\ConsolePick;
use App\Support\ConsoleSanitizer;
use App\Support\ConsoleUi;
use App\Support\Secrets;
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
use Symfony\Component\Console\Question\Question;
use RuntimeException;

final class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Update credential fields')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Entry id')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'New service')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'New username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'New password')
            ->addOption('note', null, InputOption::VALUE_OPTIONAL, 'New note');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();

        // non-interactive: patch at once
        $idOpt = (string)($input->getOption('id') ?? '');
        if ($idOpt !== '') {
            if (!Uuid::isValid($idOpt) || Uuid::fromString($idOpt)->getVersion() !== 4) {
                $output->writeln('<error>Invalid id</error>');
                return self::FAILURE;
            }

            $service = $input->getOption('service');
            $username = $input->getOption('username');
            $note = $input->getOption('note');
            $wantPasswordChange = $input->getOption('password') !== null;

            $passwordV = null;
            $pwdSecret = null;

            try {
                if (is_string($service)) {
                    $service = InputValidator::service($service);
                }
                if (is_string($username)) {
                    $username = InputValidator::username($username);
                }
                if (is_string($note)) {
                    $note = InputValidator::note($note);
                }
                if ($wantPasswordChange) {
                    $pwdSecret = Secrets::askHidden($input, $output, 'New password: ');
                    $passwordV = InputValidator::password($pwdSecret);
                }
            } catch (ValidationException $e) {
                Audit::log('input.invalid', 'fail', 410, ['code' => $e->codeShort()]);
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                if (is_string($pwdSecret)) {
                    Crypto::zeroize($pwdSecret);
                }
                return self::FAILURE;
            }

            $serviceV = is_string($service) ? $service : null;
            $usernameV = is_string($username) ? $username : null;
            $noteV = is_string($note) ? $note : null;

            $ok = VaultAccess::withUnlocked(
                $input,
                $output,
                function (array $data) use ($output, $idOpt, $serviceV, $usernameV, $passwordV, $noteV): ?array {
                    $state = VaultRepository::state($data);
                    try {
                        $res = VaultRepository::update($state, $idOpt, $serviceV, $usernameV, $passwordV, $noteV);
                    } catch (RuntimeException $e) {
                        Audit::log('entry.update.fail', 'fail', 404, ['reason' => 'not_found']);
                        $output->writeln('<error>' . $e->getMessage() . '</error>');
                        return null;
                    }

                    $u = $res['updated'];
                    $output->writeln('<info>Updated</info>');
                    $this->printData($output, $u);

                    Audit::log('entry.update', 'success', 0, ['service' => $u['service']]);
                    return $res['state'];
                }
            );

            if (is_string($pwdSecret)) {
                Crypto::zeroize($pwdSecret);
            }
            if (is_string($passwordV)) {
                Crypto::zeroize($passwordV);
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        // interactive: pick entry -> show data (password always hidden) -> loop edits
        $entryId = null;
        $view = null;

        $ok = VaultAccess::withUnlocked(
            $input,
            $output,
            function (array $data) use ($input, $output, $helper, &$entryId, &$view): ?array {
                $state = VaultRepository::state($data);
                $entries = $state['entries'] ?? [];
                if ($entries === []) {
                    $output->writeln('<comment>No entries</comment>');
                    return null;
                }

                $services = ConsolePick::services($entries);
                ConsolePick::printServices($output, $services);
                $sel = ConsolePick::askService($input, $output, $helper, $services);
                if ($sel === null) {
                    return null;
                }

                $candidates = VaultRepository::list($state, $sel);
                if ($candidates === []) {
                    $output->writeln('<error>Not found</error>');
                    return null;
                }
                $entry = ConsolePick::pickEntry($input, $output, $helper, $candidates);

                $entryId = (string)$entry['id'];
                $view = $this->sanitizeView($entry);
                return null;
            },
            // re-encrypt on read with fresh nonce after we fetched plaintext into memory
            reEncryptOnRead: true
        );

        if (!$ok || !is_string($entryId) || $entryId === '' || !is_array($view)) {
            return self::FAILURE;
        }

        $this->renderScreen($output, $view);

        while (true) {
            $output->writeln('');
            $output->writeln("Edit:\n1) Service\n2) Username\n3) Password\n4) Note\n0) Back");
            $choice = trim((string)$helper->ask($input, $output, new Question('Select number: ')));

            if ($choice === '0' || $choice === '') {
                $output->writeln('<comment>Done.</comment>');
                return self::SUCCESS;
            }

            if ($choice === '1') {
                $val = (string)$helper->ask($input, $output, new Question('New service: '));
                try {
                    $val = InputValidator::service($val);
                } catch (ValidationException $e) {
                    $this->inlineError($output, $e->getMessage());
                    continue;
                }

                $updated = $this->applyOneField($input, $output, $entryId, service: $val);
                if ($updated !== null) {
                    $view = $this->sanitizeView($updated);
                    $this->renderScreen($output, $view);
                }
                continue;
            }

            if ($choice === '2') {
                $val = (string)$helper->ask($input, $output, new Question('New username: '));
                try {
                    $val = InputValidator::username($val);
                } catch (ValidationException $e) {
                    $this->inlineError($output, $e->getMessage());
                    continue;
                }

                $updated = $this->applyOneField($input, $output, $entryId, username: $val);
                if ($updated !== null) {
                    $view = $this->sanitizeView($updated);
                    $this->renderScreen($output, $view);
                }
                continue;
            }

            if ($choice === '3') {
                $pwd = Secrets::askHidden($input, $output, 'New password: ');
                try {
                    $pwdV = InputValidator::password($pwd);
                } catch (ValidationException $e) {
                    Crypto::zeroize($pwd);
                    $this->inlineError($output, $e->getMessage());
                    continue;
                }

                $updated = $this->applyOneField($input, $output, $entryId, password: $pwdV);
                Crypto::zeroize($pwdV);
                Crypto::zeroize($pwd);
                if ($updated !== null) {
                    $view = $this->sanitizeView($updated);
                    $this->renderScreen($output, $view);
                }
                continue;
            }

            if ($choice === '4') {
                $val = (string)$helper->ask($input, $output, new Question('New note (optional): '));
                try {
                    $val = InputValidator::note($val);
                } catch (ValidationException $e) {
                    $this->inlineError($output, $e->getMessage());
                    continue;
                }

                $updated = $this->applyOneField($input, $output, $entryId, note: $val);
                if ($updated !== null) {
                    $view = $this->sanitizeView($updated);
                    $this->renderScreen($output, $view);
                }
                continue;
            }

            $this->inlineError($output, 'Invalid choice');
        }
    }

    // single-field update with separate unlock/save
    private function applyOneField(
        InputInterface  $input,
        OutputInterface $output,
        string          $entryId,
        ?string         $service = null,
        ?string         $username = null,
        ?string         $password = null,
        ?string         $note = null
    ): ?array
    {
        $updated = null;

        $ok = VaultAccess::withUnlocked(
            $input,
            $output,
            function (array $data) use ($output, $entryId, $service, $username, $password, $note, &$updated): ?array {
                $state = VaultRepository::state($data);
                try {
                    $res = VaultRepository::update($state, $entryId, $service, $username, $password, $note);
                } catch (RuntimeException $e) {
                    Audit::log('entry.update.fail', 'fail', 404, ['reason' => 'not_found']);
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                    return null;
                }
                $updated = $res['updated'];
                Audit::log('entry.update', 'success', 0, ['service' => $updated['service']]);
                return $res['state'];
            }
        );

        return ($ok && is_array($updated)) ? $updated : null;
    }

    private function renderScreen(OutputInterface $output, array $entryView): void
    {
        ConsoleUi::clear($output);
        $output->writeln('<info>Current data</info>');
        $this->printData($output, $entryView);
    }

    private function printData(OutputInterface $output, array $e): void
    {
        $output->writeln('ID: ' . ConsoleSanitizer::safe((string)$e['id']));
        $output->writeln('Service: ' . ConsoleSanitizer::safe((string)$e['service']));
        $output->writeln('Username: ' . ConsoleSanitizer::safe((string)$e['username']));
        $output->writeln('Created: ' . ConsoleSanitizer::safe((string)$e['createdAt']));
        $output->writeln('Updated: ' . ConsoleSanitizer::safe((string)$e['updatedAt']));
        $note = (string)($e['note'] ?? '');
        $output->writeln('Note: ' . ($note === '' ? '[empty]' : ConsoleSanitizer::safe($note)));
        $output->writeln('Password: ' . Secrets::hiddenPlaceholder());
    }

    private function sanitizeView(array $e): array
    {
        return [
            'id' => (string)$e['id'],
            'service' => (string)$e['service'],
            'username' => (string)$e['username'],
            'note' => (string)($e['note'] ?? ''),
            'createdAt' => (string)$e['createdAt'],
            'updatedAt' => (string)$e['updatedAt'],
            'password' => (string)($e['password'] ?? ''),
        ];
    }

    private function inlineError(OutputInterface $output, string $msg): void
    {
        $output->writeln('<error>' . $msg . '</error>');
    }
}
