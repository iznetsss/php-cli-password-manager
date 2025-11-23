<?php

declare(strict_types=1);

namespace App\Platform;

final class Paths
{
    public static function dataDir(): string
    {
        $envDir = rtrim((string)getenv('PM_DATA_DIR'), '/');
        if ($envDir !== '') {
            return $envDir;
        }

        // project-local default: php-cli-password-manager/vaults
        $projectDir = realpath(__DIR__ . '/../../..');
        if ($projectDir === false) {
            $projectDir = getcwd();
        }
        return $projectDir . '/vaults';
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

    public static function lockPath(): string
    {
        return self::dataDir() . '/vault.lock';
    }

    public static function ensureFilePerms(string $path): void
    {
        if (file_exists($path)) {
            @chmod($path, 0600);
        }
    }
}
