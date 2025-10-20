<?php

declare(strict_types=1);

namespace App\Platform;

final class Paths
{
    public static function dataDir(): string
    {
        $home = rtrim((string) getenv('HOME'), '/');
        return $home . '/.local/share/php-cli-password-manager';
    }

    public static function vaultPath(): string
    {
        return self::dataDir() . '/vault.dat';
    }

    public static function logPath(): string
    {
        return self::dataDir() . '/pm.log';
    }

    public static function ensureDataDir(): void
    {
        $dir = self::dataDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        @chmod($dir, 0700);
    }

    public static function ensureFilePerms(string $path): void
    {
        if (file_exists($path)) {
            @chmod($path, 0600);
        }
    }
}
