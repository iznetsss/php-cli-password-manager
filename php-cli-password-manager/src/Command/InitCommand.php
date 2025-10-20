<?php

declare(strict_types=1);

namespace App\Command;

use App\Platform\Paths;
use App\Security\Crypto;
use App\Vault\VaultStorage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class InitCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('init')->setDescription('Initialize a new encrypted vault');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Paths::ensureDataDir();

        $helper = new QuestionHelper();

        $q1 = new Question('Set master password: ');
        $q1->setHidden(true)->setHiddenFallback(false);
        $master1 = (string) $helper->ask($input, $output, $q1);

        $q2 = new Question('Repeat master password: ');
        $q2->setHidden(true)->setHiddenFallback(false);
        $master2 = (string) $helper->ask($input, $output, $q2);

        if ($master1 === '' || $master1 !== $master2) {
            Crypto::zeroize($master1);
            Crypto::zeroize($master2);
            $output->writeln('<error>Passwords do not match</error>');
            return self::FAILURE;
        }

        $bcryptHash = Crypto::hashMaster($master1);
        $vaultSalt = random_bytes(24);
        $vaultKey = Crypto::deriveVaultKey($master1, $vaultSalt);

        $header = [
            'version'    => 1,
            'kdf'        => [
                'name'     => 'argon2id',
                'opslimit' => 'MODERATE',
                'memlimit' => 'MODERATE',
                'salt'     => base64_encode($vaultSalt),
            ],
            'bcryptHash' => $bcryptHash,
            'createdAt'  => gmdate('c'),
            'updatedAt'  => gmdate('c'),
            'vaultId'    => Uuid::uuid7()->toString(),
        ];

        $plaintext = json_encode(['entries' => []], JSON_THROW_ON_ERROR);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = Crypto::encrypt($plaintext, $vaultKey, $nonce);

        $headerJson = json_encode($header, JSON_THROW_ON_ERROR);
        VaultStorage::save($headerJson, base64_encode($nonce), base64_encode($cipher));

        Crypto::zeroize($master1);
        Crypto::zeroize($master2);
        Crypto::zeroize($vaultKey);

        $output->writeln('<info>Vault initialized</info>');
        $output->writeln('Path: ' . Paths::vaultPath());
        return self::SUCCESS;
    }
}
