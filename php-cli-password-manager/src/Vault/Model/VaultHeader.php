<?php

declare(strict_types=1);

namespace App\Vault\Model;

use DateTimeImmutable;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class VaultHeader
{
    public function __construct(
        private int               $version,
        private KdfParams         $kdf,
        private string            $bcryptHash,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private string            $vaultId
    )
    {
        if ($version !== 1) {
            throw new RuntimeException('Unsupported header version');
        }

        $info = password_get_info($bcryptHash);
        if (($info['algoName'] ?? null) !== 'bcrypt') {
            throw new RuntimeException('bcryptHash is not a bcrypt hash');
        }

        if (!Uuid::isValid($vaultId) || Uuid::fromString($vaultId)->getVersion() !== 4) {
            throw new RuntimeException('vaultId must be UUID v4');
        }
    }

    public static function fromArray(array $a): self
    {
        $createdAt = self::parseIso((string)($a['createdAt'] ?? ''));
        $updatedAt = self::parseIso((string)($a['updatedAt'] ?? ''));

        return new self(
            (int)($a['version'] ?? 0),
            KdfParams::fromArray((array)($a['kdf'] ?? [])),
            (string)($a['bcryptHash'] ?? ''),
            $createdAt,
            $updatedAt,
            (string)($a['vaultId'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'kdf' => $this->kdf->toArray(),
            'bcryptHash' => $this->bcryptHash,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'vaultId' => $this->vaultId,
        ];
    }

    private static function parseIso(string $s): DateTimeImmutable
    {
        if ($s === '') {
            throw new RuntimeException('Missing ISO date');
        }
        $dt = DateTimeImmutable::createFromFormat(DATE_ATOM, $s);
        if ($dt === false) {
            throw new RuntimeException('Invalid ISO date');
        }
        return $dt;
    }

    public function withUpdatedNow(): self
    {
        return new self(
            $this->version,
            $this->kdf,
            $this->bcryptHash,
            $this->createdAt,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            $this->vaultId
        );
    }

    public function version(): int
    {
        return $this->version;
    }

    public function kdf(): KdfParams
    {
        return $this->kdf;
    }

    public function bcryptHash(): string
    {
        return $this->bcryptHash;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function vaultId(): string
    {
        return $this->vaultId;
    }
}
