<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Crypto;
use App\Support\Audit;
use App\Support\ConsoleSanitizer;
use App\Support\ConsoleUi;
use App\Support\Secrets;
use App\Validation\InputValidator;
use App\Validation\ValidationException;
use App\Vault\Model\CredentialInput;
use App\Vault\VaultAccess;
use App\Vault\VaultRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

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
        $helper = new QuestionHelper();

        // 1) Collect non-secret fields interactively if missing
        $serviceRaw = (string)$input->getOption('service');
        if ($serviceRaw === '') {
            $q = new Question('Service: ');
            $serviceRaw = (string)$helper->ask($input, $output, $q);
        }

        $usernameRaw = (string)$input->getOption('username');
        if ($usernameRaw === '') {
            $q = new Question('Username: ');
            $usernameRaw = (string)$helper->ask($input, $output, $q);
        }

        // Never accept password via flag
        $cliPassword = $input->getOption('password');
        if (is_string($cliPassword) && $cliPassword !== '') {
            Crypto::zeroize($cliPassword);
            $output->writeln('<comment>CLI password ignored for safety. You will be prompted securely.</comment>');
        }

        // Optional note (non-secret), ask only if not provided
        $noteRaw = (string)($input->getOption('note') ?? '');
        if ($noteRaw === '') {
            $q = new Question('Note (optional): ');
            $noteRaw = (string)$helper->ask($input, $output, $q);
        }

        // 2) Validate non-secrets first, fail early without touching secrets
        try {
            $service = InputValidator::service($serviceRaw);
            $username = InputValidator::username($usernameRaw);
            $note = InputValidator::note($noteRaw);
        } catch (ValidationException $e) {
            Audit::log('input.invalid', 'fail', 410, ['code' => $e->codeShort()]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }

        // 3) Only now ask for the secret
        $password = Secrets::askHidden($input, $output, 'Password: ');
        try {
            $password = InputValidator::password($password);
        } catch (ValidationException $e) {
            Audit::log('input.invalid', 'fail', 410, ['code' => $e->codeShort()]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            Crypto::zeroize($password);
            return self::FAILURE;
        }

        $dto = new CredentialInput($service, $username, $password, $note);

        $ok = VaultAccess::withUnlocked($input, $output, function (array $data) use ($output, $dto): ?array {
            $state = VaultRepository::state($data);
            $res = VaultRepository::add($state, $dto);

            $created = $res['created'];

            // Clean before showing the short success block
            ConsoleUi::clear($output);

            $output->writeln('<info>Added</info>');
            $output->writeln('ID: ' . ConsoleSanitizer::safe(substr($created['id'], 0, 36)));
            $output->writeln('Service: ' . ConsoleSanitizer::safe($created['service']));
            $output->writeln('Username: ' . ConsoleSanitizer::safe($created['username']));

            Audit::log('entry.add', 'success', 0, ['service' => $created['service']]);
            return $res['state'];
        });

        Crypto::zeroize($password);

        if (!$ok) {
            Audit::log('entry.add.fail', 'fail', 401, ['reason' => 'access_failed', 'service' => $serviceRaw]);
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
