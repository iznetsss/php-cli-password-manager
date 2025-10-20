<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Crypto;
use App\Vault\VaultStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class UnlockCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('unlock')->setDescription('Unlock vault with master password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();
        $q = new Question('Master password: ');
        $q->setHidden(true)->setHiddenFallback(false);
        $master = (string) $helper->ask($input, $output, $q);

        try {
            $blob = VaultStorage::load();
            $header = $blob['header'];

            $bcryptHash = (string) ($header['bcryptHash'] ?? '');
            if (!Crypto::verifyMaster($master, $bcryptHash)) {
                Crypto::zeroize($master);
                $output->writeln('<error>Invalid credentials</error>');
                return self::FAILURE;
            }

            $saltB64 = (string) ($header['kdf']['salt'] ?? '');
            $salt = base64_decode($saltB64, true);
            if ($salt === false) {
                Crypto::zeroize($master);
                $output->writeln('<error>Vault header invalid</error>');
                return self::FAILURE;
            }

            $vaultKey = Crypto::deriveVaultKey($master, $salt);
            $nonce = base64_decode($blob['nonce'], true);
            $cipher = base64_decode($blob['cipher'], true);

            if ($nonce === false || $cipher === false) {
                Crypto::zeroize($master);
                Crypto::zeroize($vaultKey);
                $output->writeln('<error>Vault data invalid</error>');
                return self::FAILURE;
            }

            $plaintext = Crypto::decrypt($cipher, $vaultKey, $nonce);
            $data = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

            Crypto::zeroize($master);
            Crypto::zeroize($vaultKey);

            $count = is_array($data['entries'] ?? null) ? count($data['entries']) : 0;
            $output->writeln('<info>Vault unlocked</info>');
            $output->writeln('Entries: ' . $count);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Crypto::zeroize($master);
            $output->writeln('<error>Unable to unlock</error>');
            return self::FAILURE;
        }
    }
}
