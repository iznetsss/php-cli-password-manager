<?php

declare(strict_types=1);

namespace App\Command;

use App\Platform\Paths;
use App\Security\Crypto;
use App\Support\Audit;
use App\Support\LoggerFactory;
use App\Support\Support;
use App\Vault\VaultStorage;
use App\Vault\Model\KdfParams;
use App\Vault\Model\VaultHeader;
use App\Vault\Model\VaultBlob;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;
use DateTimeImmutable;
use DateTimeZone;

final class InitCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('init')->setDescription('Initialize a new encrypted vault');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            Paths::ensureDataDir();

            $helper = new QuestionHelper();

            // if vault exists, ask and rewrite (delete)
            if (VaultStorage::exists()) {
                $output->writeln('<comment>Found existing vault.</comment>');
                $output->writeln('<comment>This will PERMANENTLY delete the old vault and logs and create a new one.</comment>');
                $qConfirm = new Question("1) Yes\n2) No\nSelect number: ");
                $choice = trim((string)$helper->ask($input, $output, $qConfirm));

                if ($choice !== '1') {
                    $output->writeln('<comment>Cancelled.</comment>');
                    return self::SUCCESS;
                }

                // ask current master
                $qCur = new Question('Confirm current master password: ');
                $qCur->setHidden(true)->setHiddenFallback(false);
                $currentMaster = (string)$helper->ask($input, $output, $qCur);

                try {
                    $blob = VaultStorage::load();
                    $bcryptHash = $blob->header()->bcryptHash();
                    if ($bcryptHash === '' || !Crypto::verifyMaster($currentMaster, $bcryptHash)) {
                        Crypto::zeroize($currentMaster);
                        Audit::log('vault.purge.fail', 'fail', 310, ['reason' => 'invalid_credentials']);
                        $output->writeln('<error>Invalid master password</error>');
                        return self::FAILURE;
                    }
                } catch (Throwable) {
                    Crypto::zeroize($currentMaster);
                    Audit::log('vault.purge.fail', 'fail', 311, ['reason' => 'unable_to_load']);
                    $output->writeln('<error>Unable to load existing vault</error>');
                    return self::FAILURE;
                }

                Crypto::zeroize($currentMaster);

                Audit::log('vault.purge.start', 'info', 0);
                LoggerFactory::shutdown();
                VaultStorage::purge(true);

                Support::rotateSession();
            }

            // New vault
            $q1 = new Question('Set master password: ');
            $q1->setHidden(true)->setHiddenFallback(false);
            $master1 = (string)$helper->ask($input, $output, $q1);

            $q2 = new Question('Repeat master password: ');
            $q2->setHidden(true)->setHiddenFallback(false);
            $master2 = (string)$helper->ask($input, $output, $q2);

            if ($master1 === '' || $master1 !== $master2) {
                Crypto::zeroize($master1);
                Crypto::zeroize($master2);
                Audit::log('vault.init.fail', 'fail', 101, ['reason' => 'mismatch']);
                $output->writeln('<error>Passwords do not match</error>');
                return self::FAILURE;
            }

            $bcryptHash = Crypto::hashMaster($master1);
            $vaultSalt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $vaultKey = Crypto::deriveVaultKey($master1, $vaultSalt);

            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $kdf = new KdfParams(
                'argon2id',
                'MODERATE',
                'MODERATE',
                base64_encode($vaultSalt)
            );

            $header = new VaultHeader(
                1,
                $kdf,
                $bcryptHash,
                $now,
                $now,
                Uuid::uuid4()->toString()
            );

            $plaintext = json_encode(['entries' => []], JSON_THROW_ON_ERROR);
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = Crypto::encrypt($plaintext, $vaultKey, $nonce);

            $blob = new VaultBlob(
                $header,
                base64_encode($nonce),
                base64_encode($cipher)
            );

            VaultStorage::save($blob);

            Crypto::zeroize($master1);
            Crypto::zeroize($master2);
            Crypto::zeroize($vaultKey);

            Audit::log('vault.init', 'success', 0, ['vaultId' => $header->vaultId()]);
            $output->writeln('<info>Vault initialized</info>');
            $output->writeln('Path: ' . Paths::vaultPath());
            return self::SUCCESS;
        } catch (Throwable) {
            Audit::log('vault.init.fail', 'fail', 199, ['reason' => 'exception']);
            $output->writeln('<error>Unable to initialize</error>');
            return self::FAILURE;
        }
    }
}
