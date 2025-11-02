<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Crypto;
use App\Support\Audit;
use App\Support\ConsoleSanitizer;
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
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Entry id')
            ->addOption('service', null, InputOption::VALUE_OPTIONAL, 'New service')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'New username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'New password')
            ->addOption('note', null, InputOption::VALUE_OPTIONAL, 'New note');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();

        $id = (string)$input->getOption('id');
        if ($id === '') {
            $q = new Question('Entry ID: ');
            $id = (string)$helper->ask($input, $output, $q);
        }
        if (!Uuid::isValid($id) || Uuid::fromString($id)->getVersion() !== 4) {
            $output->writeln('<error>Invalid id</error>');
            return self::FAILURE;
        }

        $service = $input->getOption('service');
        $username = $input->getOption('username');
        $note = $input->getOption('note');

        // Toggle by presence of the flag, read secret via hidden prompt
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
            if (is_string($pwdSecret)) Crypto::zeroize($pwdSecret);
            return self::FAILURE;
        }

        $serviceV = is_string($service) ? $service : null;
        $usernameV = is_string($username) ? $username : null;
        $noteV = is_string($note) ? $note : null;

        $ok = VaultAccess::withUnlocked(
            $input,
            $output,
            function (array $data) use ($output, $id, $serviceV, $usernameV, $passwordV, $noteV): ?array {
                $state = VaultRepository::state($data);
                try {
                    $res = VaultRepository::update($state, $id, $serviceV, $usernameV, $passwordV, $noteV);
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
                if ($noteV !== null) {
                    $output->writeln('Note: [updated]');
                }
                if ($passwordV !== null) {
                    $output->writeln('Password: [updated]');
                }

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
}
