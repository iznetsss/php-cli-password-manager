<?php
declare(strict_types=1);

namespace App\Validation;

final class InputValidator
{
    /** service: ^[A-Za-z0-9._:-]{1,255}$ (trim then strict ASCII whitelist) */
    public static function service(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            throw new ValidationException('SVC_EMPTY', 'Invalid service');
        }
        if (strlen($s) > 255) {
            throw new ValidationException('SVC_LEN', 'Invalid service');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $s)) {
            throw new ValidationException('SVC_FORMAT', 'Invalid service');
        }
        return $s;
    }

    /** username: length 1..255, remove control chars (C0 + DEL), then trim */
    public static function username(string $raw): string
    {
        $u = self::stripControls($raw);
        $u = trim($u);
        $len = mb_strlen($u, 'UTF-8');
        if ($len < 1) {
            throw new ValidationException('USR_EMPTY', 'Invalid username');
        }
        if ($len > 255) {
            throw new ValidationException('USR_LEN', 'Invalid username');
        }
        return $u;
    }


    /** password: bytes length 1..4096, must be valid UTF-8 */
    public static function password(string $raw): string
    {
        $length = strlen($raw);
        if ($length < 1 || $length > 4096) {
            throw new ValidationException('PWD_LEN', 'Invalid password');
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            // do not allow binary / non-UTF-8, JSON would fail
            throw new ValidationException('PWD_UTF8', 'Invalid password');
        }

        return $raw;
    }

    /** note: remove control chars, trim, cut to ≤250 chars (UTF-8 safe) */
    public static function note(?string $raw): string
    {
        $n = trim(self::stripControls((string)$raw));
        if ($n === '') {
            return '';
        }
        if (mb_strlen($n, 'UTF-8') > 250) {
            // hard cut to 250 characters (not bytes)
            $n = mb_substr($n, 0, 250, 'UTF-8');
        }
        return $n;
    }

    /** helper: drop C0 and DEL; keep everything else (incl. spaces, emoji, etc.) */
    private static function stripControls(string $s): string
    {
        // remove all \x00-\x1F and \x7F
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    }
}
