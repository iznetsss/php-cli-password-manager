<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class Crypto
{
    public static function hashMaster(string $master): string
    {
        return password_hash($master, PASSWORD_BCRYPT);
    }

    public static function verifyMaster(string $master, string $bcryptHash): bool
    {
        return password_verify($master, $bcryptHash);
    }

    private static function mapOps(string $level): int
    {
        $lvl = strtoupper($level);
        return match ($lvl) {
            'LIGHT' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            'HEAVY' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
            'MEDIUM', 'MODERATE' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            default => SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
        };
    }

    private static function mapMem(string $level): int
    {
        $lvl = strtoupper($level);
        return match ($lvl) {
            'LIGHT' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            'HEAVY' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
            'MEDIUM', 'MODERATE' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            default => SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
        };
    }

    public static function deriveVaultKey(
        string $master,
        string $salt,
        string $level = 'MEDIUM',
        int    $keyLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
    ): string
    {
        if (strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            throw new RuntimeException('Invalid salt length');
        }

        return sodium_crypto_pwhash(
            $keyLen,
            $master,
            $salt,
            self::mapOps($level),
            self::mapMem($level),
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    public static function encrypt(string $plaintext, string $key, string $nonce, string $aad): string
    {
        return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
    }

    public static function decrypt(string $ciphertext, string $key, string $nonce, string $aad): string
    {
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $key);
        if ($plain === false) {
            throw new RuntimeException('Decryption failed');
        }
        return $plain;
    }

    public static function zeroize(string &$secret): void
    {
        sodium_memzero($secret);
    }
}
