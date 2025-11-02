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

        $idOpt = (string)($input->getOption('id') ?? '');

        // Interactive mode when id is not provided
        if ($idOpt === '') {
            $pwdTmp = null;
            $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($input, $output, $helper, &$pwdTmp): ?array {
                $state = VaultRepository::state($data);
                $entries = $state['entries'] ?? [];
                if ($entries === []) {
                    $output->writeln('<comment>No entries</comment>');
                    return null;
                }

                // pick service -> entry
                $services = ConsolePick::services($entries);
                ConsolePick::printServices($output, $services);
                $sel = ConsolePick::askService($input, $output, $helper, $services);
                if ($sel === null) return null;

                $candidates = VaultRepository::list($state, $sel);
                if ($candidates === []) {
                    $output->writeln('<error>Not found</error>');
                    return null;
                }
                $entry = ConsolePick::pickEntry($input, $output, $helper, $candidates);

                // show current data
                ConsoleUi::clear($output);
                $output->writeln('<info>Current data</info>');
                $output->writeln('ID: ' . ConsoleSanitizer::safe($entry['id']));
                $output->writeln('Service: ' . ConsoleSanitizer::safe($entry['service']));
                $output->writeln('Username: ' . ConsoleSanitizer::safe($entry['username']));
                $output->writeln('Created: ' . ConsoleSanitizer::safe($entry['createdAt']));
                $output->writeln('Updated: ' . ConsoleSanitizer::safe($entry['updatedAt']));
                if (($entry['note'] ?? '') !== '') {
                    $output->writeln('Note: [saved]');
                }
                $output->writeln('Password: ' . Secrets::hiddenPlaceholder());

                $newService = null;
                $newUser = null;
                $newPass = null;
                $newNote = null;

                while (true) {
                    $output->writeln('');
                    $output->writeln("Edit:\n1) Service\n2) Username\n3) Password\n4) Note\n5) Save\n0) Cancel");
                    $choice = trim((string)$helper->ask($input, $output, new Question('Select number: ')));

                    if ($choice === '0' || $choice === '') {
                        if (is_string($pwdTmp)) Crypto::zeroize($pwdTmp);
                        $output->writeln('<comment>Cancelled.</comment>');
                        return null;
                    }

                    if ($choice === '1') {
                        $val = (string)$helper->ask($input, $output, new Question('New service: '));
                        try {
                            $newService = InputValidator::service($val);
                            $output->writeln('<info>Queued</info>: service');
                        } catch (ValidationException $e) {
                            $output->writeln('<error>' . $e->getMessage() . '</error>');
                        }
                        continue;
                    }

                    if ($choice === '2') {
                        $val = (string)$helper->ask($input, $output, new Question('New username: '));
                        try {
                            $newUser = InputValidator::username($val);
                            $output->writeln('<info>Queued</info>: username');
                        } catch (ValidationException $e) {
                            $output->writeln('<error>' . $e->getMessage() . '</error>');
                        }
                        continue;
                    }

                    if ($choice === '3') {
                        $pwd = Secrets::askHidden($input, $output, 'New password: ');
                        try {
                            $newPass = InputValidator::password($pwd);
                            $pwdTmp = $pwd;
                            $output->writeln('<info>Queued</info>: password');
                        } catch (ValidationException $e) {
                            $output->writeln('<error>' . $e->getMessage() . '</error>');
                            Crypto::zeroize($pwd);
                        }
                        continue;
                    }

                    if ($choice === '4') {
                        $val = (string)$helper->ask($input, $output, new Question('New note (optional): '));
                        try {
                            $newNote = InputValidator::note($val);
                            $output->writeln('<info>Queued</info>: note');
                        } catch (ValidationException $e) {
                            $output->writeln('<error>' . $e->getMessage() . '</error>');
                        }
                        continue;
                    }

                    if ($choice === '5') {
                        if ($newService === null && $newUser === null && $newPass === null && $newNote === null) {
                            $output->writeln('<comment>Nothing to update</comment>');
                            continue;
                        }

                        try {
                            $res = VaultRepository::update($state, (string)$entry['id'], $newService, $newUser, $newPass, $newNote);
                        } catch (RuntimeException $e) {
                            if (is_string($pwdTmp)) Crypto::zeroize($pwdTmp);
                            $output->writeln('<error>' . $e->getMessage() . '</error>');
                            return null;
                        }

                        if (is_string($newPass)) Crypto::zeroize($newPass);
                        if (is_string($pwdTmp)) Crypto::zeroize($pwdTmp);

                        $u = $res['updated'];

                        // show updated block
                        ConsoleUi::clear($output);
                        $output->writeln('<info>Your updated data</info>');
                        $output->writeln('ID: ' . ConsoleSanitizer::safe($u['id']));
                        $output->writeln('Service: ' . ConsoleSanitizer::safe($u['service']));
                        $output->writeln('Username: ' . ConsoleSanitizer::safe($u['username']));
                        $output->writeln('Created: ' . ConsoleSanitizer::safe($u['createdAt']));
                        $output->writeln('Updated: ' . ConsoleSanitizer::safe($u['updatedAt']));
                        if ($newNote !== null) {
                            $output->writeln('Note: [updated]');
                        } elseif (($u['note'] ?? '') !== '') {
                            $output->writeln('Note: [saved]');
                        }
                        if ($newPass !== null) {
                            $output->writeln('Password: [updated]');
                        } else {
                            $output->writeln('Password: ' . Secrets::hiddenPlaceholder());
                        }

                        Audit::log('entry.update', 'success', 0, ['service' => $u['service']]);

                        // pause then wipe screen; vault already re-encrypted on save
                        ConsoleUi::waitAnyKey($input, $output);
                        ConsoleUi::clear($output);

                        return $res['state'];
                    }

                    $output->writeln('<error>Invalid choice</error>');
                }
            }, reEncryptOnRead: true);

            return $ok ? self::SUCCESS : self::FAILURE;
        }

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
            if (is_string($service)) $service = InputValidator::service($service);
            if (is_string($username)) $username = InputValidator::username($username);
            if (is_string($note)) $note = InputValidator::note($note);
            if ($wantPasswordChange) {
                $pwdSecret = Secrets::askHidden($input, $output, 'New password: ');
                $passwordV = InputValidator::password($pwdSecret);
            }
        } catch (ValidationException $e) {
            Audit::log('input.invalid', 'fail', 410, ['code' => $e->codeShort()]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            if (is_string($pwdSecret)) Crypto::zeroize($pwdSecret);
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
                $output->writeln('ID: ' . ConsoleSanitizer::safe($u['id']));
                $output->writeln('Service: ' . ConsoleSanitizer::safe($u['service']));
                $output->writeln('Username: ' . ConsoleSanitizer::safe($u['username']));
                if ($noteV !== null) $output->writeln('Note: [updated]');
                if ($passwordV !== null) $output->writeln('Password: [updated]');

                Audit::log('entry.update', 'success', 0, ['service' => $u['service']]);
                return $res['state'];
            }
        );

        if (is_string($pwdSecret)) Crypto::zeroize($pwdSecret);
        if (is_string($passwordV)) Crypto::zeroize($passwordV);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
