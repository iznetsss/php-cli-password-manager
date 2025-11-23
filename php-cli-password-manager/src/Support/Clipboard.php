<?php

declare(strict_types=1);

namespace App\Support;

final class Clipboard
{
    public static function isSupported(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    public static function isAvailable(): bool
    {
        if (!self::isSupported()) {
            return false;
        }

        $display = getenv('DISPLAY');
        if (!is_string($display) || trim($display) === '') {
            return false;
        }

        $result = @shell_exec('command -v xclip 2>/dev/null');
        return is_string($result) && trim($result) !== '';
    }

    public static function copy(string $text): bool
    {
        if (!self::isAvailable()) {
            return false;
        }

        $descriptors = [
            0 => ['pipe', 'w'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $env = [
            'DISPLAY' => (string)getenv('DISPLAY'),
        ];

        $process = @proc_open('xclip -selection clipboard -in', $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            return false;
        }

        try {
            if (!isset($pipes[0]) || !is_resource($pipes[0])) {
                @proc_close($process);
                return false;
            }

            $written = @fwrite($pipes[0], $text);
            @fclose($pipes[0]);

            if ($written === false) {
                @proc_close($process);
                return false;
            }

            // Do not wait for xclip, avoid blocking
            return true;
        } catch (\Throwable) {
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                @fclose($pipes[0]);
            }
            @proc_terminate($process);
            return false;
        }
    }
}
