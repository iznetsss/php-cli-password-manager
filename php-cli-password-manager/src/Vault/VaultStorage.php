<?php

declare(strict_types=1);

namespace App\Vault;

use App\Platform\Paths;
use App\Platform\Permissions;
use App\Platform\ProcessLock;
use App\Support\Audit;
use App\Vault\Model\VaultBlob;
use RuntimeException;
use Throwable;

final class VaultStorage
{
    public static function save(VaultBlob $blob): void
    {
        try {
            Paths::ensureDataDir();
            $dir = Paths::dataDir();
            Permissions::assertDataDirSecure($dir);

            $path = Paths::vaultPath();
            $tmp = $path . '.tmp';

            $json = json_encode($blob->toArray(), JSON_THROW_ON_ERROR);

            file_put_contents($tmp, $json, LOCK_EX);
            @chmod($tmp, 0600);

            rename($tmp, $path);
            @chmod($path, 0600);

            Permissions::assertFileSecure($path, 0o600);

            Audit::log('vault.save', 'success', 0);
        } catch (Throwable $e) {
            @is_file($tmp ?? '') && @unlink($tmp);
            Audit::log('vault.save.fail', 'fail', 301, ['reason' => 'exception']);
            throw $e;
        }
    }

    public static function load(): VaultBlob
    {
        $dir = Paths::dataDir();
        Permissions::assertDataDirSecure($dir);

        $path = Paths::vaultPath();
        Permissions::assertFileSecure($path, 0o600);

        if (!file_exists($path)) {
            throw new RuntimeException('Vault not found');
        }

        $raw = file_get_contents($path);
        $data = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);

        return VaultBlob::fromArray((array)$data);
    }

    public static function exists(): bool
    {
        Paths::ensureDataDir();
        return is_file(Paths::vaultPath());
    }

    public static function purge(bool $includeLogs = true): void
    {
        // Allow only when the app holds the lock
        if (!ProcessLock::isAcquired()) {
            throw new RuntimeException('Purge requires application lock');
        }

        Paths::ensureDataDir();

        $files = [
            Paths::vaultPath(),
            Paths::vaultPath() . '.tmp',
        ];
        if ($includeLogs) {
            $files[] = Paths::logPath();
        }

        $failed = [];
        foreach ($files as $file) {
            if (is_file($file) && !@unlink($file)) {
                $failed[] = $file;
            }
        }

        if ($failed !== []) {
            throw new RuntimeException('Failed to delete: ' . implode(', ', $failed));
        }
    }
}
