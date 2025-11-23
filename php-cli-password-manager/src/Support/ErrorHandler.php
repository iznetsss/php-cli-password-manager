<?php

declare(strict_types=1);

namespace App\Support;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1'); // show errors to user in console

        // Log fatals on shutdown, do not change console output
        register_shutdown_function(static function (): void {
            $lastError = error_get_last();
            if ($lastError === null) {
                return;
            }
            LoggerFactory::get()->error('shutdown_error', $lastError);
        });

    }
}
