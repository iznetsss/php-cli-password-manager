<?php

declare(strict_types=1);

namespace App\Vault\Model;

use RuntimeException;

final readonly class KdfParams
{
    public function __construct(
        private string $name,
        private string $opslimit,
        private string $memlimit,
        private string $saltB64
    )
    {
        if ($name !== 'argon2id') {
            throw new RuntimeException('KDF name must be argon2id');
        }
        if (strtoupper($opslimit) !== 'MEDIUM') {
            throw new RuntimeException('Invalid opslimit');
        }
        if (strtoupper($memlimit) !== 'MEDIUM') {
            throw new RuntimeException('Invalid memlimit');
        }
        $raw = base64_decode($saltB64, true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            throw new RuntimeException('Invalid KDF salt');
        }
    }

    public static function fromArray(array $a): self
    {
        return new self(
            (string)($a['name'] ?? ''),
            (string)($a['opslimit'] ?? ''),
            (string)($a['memlimit'] ?? ''),
            (string)($a['salt'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'opslimit' => $this->opslimit,
            'memlimit' => $this->memlimit,
            'salt' => $this->saltB64,
        ];
    }

    public function saltRaw(): string
    {
        $raw = base64_decode($this->saltB64, true);
        if ($raw === false) {
            throw new RuntimeException('Invalid base64 salt');
        }
        return $raw;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function opslimit(): string
    {
        return $this->opslimit;
    }

    public function memlimit(): string
    {
        return $this->memlimit;
    }

    public function saltB64(): string
    {
        return $this->saltB64;
    }
}
