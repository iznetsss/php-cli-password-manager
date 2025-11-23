<?php

declare(strict_types=1);

namespace App\Platform;

final class ProcessLock
{
    /** @var resource|null */
    private static $fileHandle = null;

    public static function acquire(): bool
    {
        Paths::ensureDataDir();
        $lockPath = Paths::lockPath();

        $fileHandle = @fopen($lockPath, 'c');
        if (!is_resource($fileHandle)) {
            return false;
        }
        @chmod($lockPath, 0600);

        if (!@flock($fileHandle, LOCK_EX | LOCK_NB)) {
            @fclose($fileHandle);
            return false;
        }

        self::$fileHandle = $fileHandle;
        return true;
    }

    public static function isAcquired(): bool
    {
        return is_resource(self::$fileHandle);
    }

    public static function release(): void
    {
        if (!is_resource(self::$fileHandle)) {
            return;
        }
        @flock(self::$fileHandle, LOCK_UN);
        @fclose(self::$fileHandle);
        self::$fileHandle = null;
    }
}
