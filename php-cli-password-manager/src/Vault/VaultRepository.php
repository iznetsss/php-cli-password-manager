<?php
declare(strict_types=1);

namespace App\Vault;

use App\Validation\InputValidator;
use App\Vault\Model\CredentialInput;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class VaultRepository
{
    /** Normalize state to { entries: [] } */
    public static function state(array $data): array
    {
        $entries = [];
        foreach ((array)($data['entries'] ?? []) as $e) {
            $norm = self::normalizeEntry((array)$e);
            if ($norm !== null) {
                $entries[] = $norm;
            }
        }
        return ['entries' => $entries];
    }

    /** List entries, optional strict service filter */
    public static function list(array $state, ?string $service = null): array
    {
        $entries = $state['entries'] ?? [];
        if ($service === null || $service === '') {
            return $entries;
        }
        $out = [];
        foreach ($entries as $e) {
            if (($e['service'] ?? '') === $service) {
                $out[] = $e;
            }
        }
        return $out;
    }

    /** Add entry; returns ['state' => ..., 'created' => entry] */
    public static function add(array $state, CredentialInput $dto): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format(DATE_ATOM);
        $entry = [
            'id' => Uuid::uuid4()->toString(),
            'service' => $dto->service,
            'username' => $dto->username,
            'password' => $dto->password,
            'note' => $dto->note,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        $entries = $state['entries'] ?? [];
        $entries[] = $entry;
        return ['state' => ['entries' => $entries], 'created' => $entry];
    }

    public static function getById(array $state, string $id): ?array
    {
        foreach ($state['entries'] ?? [] as $e) {
            if (($e['id'] ?? '') === $id) {
                return $e;
            }
        }
        return null;
    }

    /** Update fields by id; returns ['state' => ..., 'updated' => entry] */
    public static function update(
        array   $state,
        string  $id,
        ?string $service,
        ?string $username,
        ?string $password,
        ?string $note
    ): array
    {
        $entries = $state['entries'] ?? [];
        $found = false;

        if ($service === null && $username === null && $password === null && $note === null) {
            throw new RuntimeException('Nothing to update');
        }

        foreach ($entries as &$e) {
            if (($e['id'] ?? '') !== $id) {
                continue;
            }
            $found = true;
            $changed = false;

            if ($service !== null) {
                $e['service'] = InputValidator::service($service);
                $changed = true;
            }
            if ($username !== null) {
                $e['username'] = InputValidator::username($username);
                $changed = true;
            }
            if ($password !== null) {
                $e['password'] = InputValidator::password($password);
                $changed = true;
            }
            if ($note !== null) {
                $e['note'] = InputValidator::note($note);
                $changed = true;
            }

            if ($changed) {
                $e['updatedAt'] = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format(DATE_ATOM);
            }
            $updated = $e;
            unset($e);
            return ['state' => ['entries' => $entries], 'updated' => $updated];
        }
        unset($e);

        if (!$found) {
            throw new RuntimeException('Not found');
        }
        // unreachable
        return ['state' => ['entries' => $entries], 'updated' => null];
    }

    /** Delete by id; returns ['state' => ..., 'deleted' => true] */
    public static function delete(array $state, string $id): array
    {
        $entries = $state['entries'] ?? [];
        $out = [];
        $deleted = false;
        foreach ($entries as $e) {
            if (($e['id'] ?? '') === $id) {
                $deleted = true;
                continue;
            }
            $out[] = $e;
        }
        if (!$deleted) {
            throw new RuntimeException('Not found');
        }
        return ['state' => ['entries' => $out], 'deleted' => true];
    }

    private static function normalizeEntry(array $e): ?array
    {
        $id = (string)($e['id'] ?? '');
        $service = (string)($e['service'] ?? '');
        $username = (string)($e['username'] ?? '');
        $password = (string)($e['password'] ?? '');
        $note = (string)($e['note'] ?? '');
        $createdAt = (string)($e['createdAt'] ?? '');
        $updatedAt = (string)($e['updatedAt'] ?? '');

        if ($id === '' || !Uuid::isValid($id) || Uuid::fromString($id)->getVersion() !== 4) {
            return null;
        }
        if ($service === '' || $username === '' || $password === '' || $createdAt === '' || $updatedAt === '') {
            return null;
        }
        return compact('id', 'service', 'username', 'password', 'note', 'createdAt', 'updatedAt');
    }
}
