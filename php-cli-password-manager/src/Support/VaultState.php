<?php

declare(strict_types=1);

namespace App\Support;

final class VaultState
{
    private static bool $unlocked = false;

    public static function isUnlocked(): bool
    {
        return self::$unlocked;
    }

    public static function unlock(): void
    {
        self::$unlocked = true;
    }

    public static function lock(): void
    {
        self::$unlocked = false;
    }
}
