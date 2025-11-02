<?php

declare(strict_types=1);

namespace App\Platform;

use RuntimeException;

final class Permissions
{
    // Validate data dir is 0700 and owned by current user
    public static function assertDataDirSecure(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException('Data dir missing');
        }

        self::assertOwner($dir);
        self::assertMode($dir, 0o700);

        @chmod($dir, 0700);
    }

    public static function assertFileSecure(string $path, int $requiredModeOctal): void
    {
        if (!is_file($path)) {
            return;
        }

        self::assertOwner($path);
        self::assertMode($path, $requiredModeOctal);

        @chmod($path, $requiredModeOctal);
    }

    private static function assertOwner(string $path): void
    {
        if (function_exists('posix_geteuid') && function_exists('fileowner')) {
            $owner = @fileowner($path);
            $euid = @posix_geteuid();
            if ($owner !== false && $euid !== false && $owner !== $euid) {
                throw new RuntimeException('Ownership mismatch');
            }
        }
    }

    private static function assertMode(string $path, int $requiredModeOctal): void
    {
        $perms = @fileperms($path);
        if ($perms === false) {
            throw new RuntimeException('Cannot read permissions');
        }

        $mode = $perms & 0o777;
        // Must not be broader than required
        if (($mode | $requiredModeOctal) !== $requiredModeOctal) {
            throw new RuntimeException('Insecure permissions');
        }
    }
}
