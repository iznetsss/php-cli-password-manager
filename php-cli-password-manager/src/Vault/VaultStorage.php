<?php

declare(strict_types=1);

namespace App\Vault;

use App\Platform\Paths;

final class VaultStorage
{
    public static function save(string $headerJson, string $nonceB64, string $cipherB64): void
    {
        Paths::ensureDataDir();
        $path = Paths::vaultPath();
        $tmp = $path . '.tmp';

        $blob = json_encode([
            'header' => json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR),
            'nonce'  => $nonceB64,
            'cipher' => $cipherB64,
        ], JSON_THROW_ON_ERROR);

        file_put_contents($tmp, $blob, LOCK_EX);
        @chmod($tmp, 0600);
        rename($tmp, $path);
        Paths::ensureFilePerms($path);
    }

    /**
     * @return array{header: array, nonce: string, cipher: string}
     */
    public static function load(): array
    {
        $path = Paths::vaultPath();
        if (!file_exists($path)) {
            throw new \RuntimeException('Vault not found');
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['header'], $data['nonce'], $data['cipher'])) {
            throw new \RuntimeException('Corrupt vault file');
        }

        return [
            'header' => $data['header'],
            'nonce'  => (string) $data['nonce'],
            'cipher' => (string) $data['cipher'],
        ];
    }
}
