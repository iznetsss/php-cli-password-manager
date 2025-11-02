<?php

declare(strict_types=1);

namespace App\Support;

use Ramsey\Uuid\Uuid;

final class Support
{
    private static ?string $sessionId = null;

    public static function id(): string
    {
        if (self::$sessionId === null) {
            self::$sessionId = Uuid::uuid4()->toString();
        }
        return self::$sessionId;
    }

    public static function userId(): int
    {
        if (function_exists('posix_geteuid')) {
            $euid = @posix_geteuid();
            if (is_int($euid)) {
                return $euid;
            }
        }
        return 0;
    }

    public static function rotateSession(): void
    {
        self::$sessionId = Uuid::uuid4()->toString();
    }
}
