<?php

declare(strict_types=1);

namespace App\Vault;

use App\Security\Crypto;
use App\Support\Audit;
use App\Support\Secrets;
use App\Support\Support;
use App\Vault\Model\VaultBlob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class VaultAccess
{
    public static function withUnlocked(
        InputInterface  $input,
        OutputInterface $output,
        callable        $callback,
        bool            $reEncryptOnRead = false
    ): bool
    {
        if (!VaultStorage::exists()) {
            Audit::log('vault.access.fail', 'fail', 200, ['reason' => 'not_initialized']);
            $output->writeln('<error>Vault not initialized. Run "pm init" first.</error>');
            return false;
        }

        $master = Secrets::askHidden($input, $output, 'Master password: ');

        $vaultKey = null;
        $plaintext = null;
        $newPlaintext = null;

        try {
            $blob = VaultStorage::load();
            $header = $blob->header();

            $bcryptHash = $header->bcryptHash();
            if ($bcryptHash === '' || !Crypto::verifyMaster($master, $bcryptHash)) {
                Audit::log('vault.access.fail', 'fail', 201, ['reason' => 'invalid_credentials']);
                $output->writeln('<error>Invalid credentials</error>');
                return false;
            }

            $salt = $header->kdf()->saltRaw();
            $vaultKey = Crypto::deriveVaultKey($master, $salt, 'MEDIUM');

            $nonce = $blob->nonceRaw();
            $cipher = $blob->cipherRaw();

            $aad = hash('sha256', json_encode($header->toArray(), \JSON_THROW_ON_ERROR), true);
            $plaintext = Crypto::decrypt($cipher, $vaultKey, $nonce, $aad);
            $data = json_decode($plaintext, true, 512, \JSON_THROW_ON_ERROR);

            $result = $callback($data);

            if (is_array($result)) {
                $newHeader = $header->withUpdatedNow();
                $newPlaintext = json_encode($result, \JSON_THROW_ON_ERROR);
                $newNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
                $newAad = hash('sha256', json_encode($newHeader->toArray(), \JSON_THROW_ON_ERROR), true);
                $newCipher = Crypto::encrypt($newPlaintext, $vaultKey, $newNonce, $newAad);

                $newBlob = new VaultBlob(
                    $newHeader,
                    base64_encode($newNonce),
                    base64_encode($newCipher)
                );
                VaultStorage::save($newBlob);
            } elseif ($reEncryptOnRead) {
                $newNonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
                $newAad = hash('sha256', json_encode($header->toArray(), \JSON_THROW_ON_ERROR), true);
                $newCipher = Crypto::encrypt($plaintext, $vaultKey, $newNonce, $newAad);

                $newBlob = new VaultBlob(
                    $header,
                    base64_encode($newNonce),
                    base64_encode($newCipher)
                );
                VaultStorage::save($newBlob);
            }

            Support::rotateSession();
            Audit::log('vault.access', 'success', 0);
            return true;
        } catch (Throwable $e) {
            Audit::log('vault.access.fail', 'fail', 299, ['reason' => 'exception']);
            $output->writeln('<error>Unable to access vault</error>');
            return false;
        } finally {
            if (is_string($master)) {
                Crypto::zeroize($master);
            }
            if (is_string($vaultKey)) {
                Crypto::zeroize($vaultKey);
            }
            if (is_string($plaintext)) {
                Crypto::zeroize($plaintext);
            }
            if (is_string($newPlaintext)) {
                Crypto::zeroize($newPlaintext);
            }
            Audit::log('vault.lock', 'success', 0);
        }
    }
}
