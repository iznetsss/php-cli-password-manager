<?php

declare(strict_types=1);

namespace App\Security;

final class Crypto
{
    private const AAD = 'php-cli-password-manager:v1';

    public static function hashMaster(string $master): string
    {
        return password_hash($master, PASSWORD_BCRYPT);
    }

    public static function verifyMaster(string $master, string $bcryptHash): bool
    {
        return password_verify($master, $bcryptHash);
    }

    public static function deriveVaultKey(string $master, string $salt, int $keyLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES): string
    {
        return sodium_crypto_pwhash(
            $keyLen,
            $master,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    public static function encrypt(string $plaintext, string $key, string $nonce): string
    {
        return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, self::AAD, $nonce, $key);
    }

    public static function decrypt(string $ciphertext, string $key, string $nonce): string
    {
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, self::AAD, $nonce, $key);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plain;
    }

    public static function zeroize(string &$secret): void
    {
        sodium_memzero($secret);
    }
}
