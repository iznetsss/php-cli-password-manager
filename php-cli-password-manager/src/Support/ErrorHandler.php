<?php

declare(strict_types=1);

namespace App\Support;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);

        // Never show PHP engine errors to user
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');

        // Log fatals on shutdown, do not touch console output
        register_shutdown_function(static function (): void {
            $lastError = error_get_last();
            if ($lastError === null) {
                return;
            }
            LoggerFactory::get()->error('shutdown_error', $lastError);
        });
    }
}
