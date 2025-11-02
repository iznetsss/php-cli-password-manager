<?php

declare(strict_types=1);

namespace App\Support;

use App\Platform\Paths;
use App\Platform\Permissions;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Throwable;

final class LoggerFactory
{
    private static ?LoggerInterface $logger = null;

    public static function get(): LoggerInterface
    {
        if (self::$logger !== null) {
            return self::$logger;
        }

        Paths::ensureDataDir();
        $dir = Paths::dataDir();
        Permissions::assertDataDirSecure($dir);

        $logPath = Paths::logPath();
        if (!file_exists($logPath)) {
            touch($logPath);
            @chmod($logPath, 0600);
        }
        Permissions::assertFileSecure($logPath, 0o600);

        $handler = new StreamHandler($logPath, Logger::INFO, true);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));

        $logger = new Logger('pm');

        // add base fields
        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            $ctx = $record->context;
            $ctx['ts'] = $record->datetime->format(\DATE_ATOM);
            $ctx['userId'] = Support::userId();
            $ctx['sessionId'] = Support::id();
            $ctx['ip'] = 'local';
            return $record->with(context: $ctx);
        });

        // redact secrets
        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            $ctx = $record->context;
            $keys = ['password', 'secret', 'key', 'token', 'master', 'vaultKey'];
            foreach ($keys as $k) {
                if (array_key_exists($k, $ctx)) {
                    $ctx[$k] = '[redacted]';
                }
            }
            return $record->with(context: $ctx);
        });

        $logger->pushHandler($handler);

        self::$logger = $logger;
        return self::$logger;
    }

    public static function shutdown(): void
    {
        if (self::$logger === null) {
            return;
        }

        $logger = self::$logger;
        if ($logger instanceof Logger) {
            foreach ($logger->getHandlers() as $handler) {
                if (is_object($handler) && method_exists($handler, 'close')) {
                    try {
                        $handler->close();
                    } catch (Throwable) {
                    }
                }
            }
        }

        self::$logger = null;
    }
}
