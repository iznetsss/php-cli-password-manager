<?php

declare(strict_types=1);

namespace App\Support;

final class Audit
{
    public static function log(string $event, string $outcome, int $code, array $context = []): void
    {
        $ctx = array_merge([
            'event' => $event,
            'outcome' => $outcome,
            'code' => $code,
        ], $context);

        LoggerFactory::get()->info($event, $ctx);
    }
}
