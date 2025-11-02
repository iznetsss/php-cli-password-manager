<?php
declare(strict_types=1);

namespace App\Support;

final class ConsoleSanitizer
{
    public static function safe(string $s): string
    {
        // Strip ANSI escape sequences (CSI + extended)
        $noAnsi = preg_replace('#\x1B\[[0-9;?]*[ -/]*[@-~]#u', '', $s) ?? '';

        // Remove C0 controls + DEL
        return preg_replace('#[\x00-\x1F\x7F]#u', '', $noAnsi) ?? '';
    }
}