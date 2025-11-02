<?php

declare(strict_types=1);

namespace App\Vault\Model;

use RuntimeException;

final readonly class VaultBlob
{
    public function __construct(
        private VaultHeader $header,
        private string      $nonceB64,
        private string      $cipherB64
    )
    {
        $nonce = base64_decode($nonceB64, true);
        if ($nonce === false || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new RuntimeException('Invalid nonce');
        }
        $cipher = base64_decode($cipherB64, true);
        if ($cipher === false || $cipher === '') {
            throw new RuntimeException('Invalid cipher');
        }
    }

    public static function fromArray(array $a): self
    {
        if (!isset($a['header'], $a['nonce'], $a['cipher'])) {
            throw new RuntimeException('Corrupt vault file');
        }
        return new self(
            VaultHeader::fromArray((array)$a['header']),
            (string)$a['nonce'],
            (string)$a['cipher']
        );
    }

    public function toArray(): array
    {
        return [
            'header' => $this->header->toArray(),
            'nonce' => $this->nonceB64,
            'cipher' => $this->cipherB64,
        ];
    }

    public function header(): VaultHeader
    {
        return $this->header;
    }

    public function nonceB64(): string
    {
        return $this->nonceB64;
    }

    public function cipherB64(): string
    {
        return $this->cipherB64;
    }

    public function nonceRaw(): string
    {
        /** @var string|false $raw */
        $raw = base64_decode($this->nonceB64, true);
        if ($raw === false) {
            throw new RuntimeException('Invalid nonce b64');
        }
        return $raw;
    }

    public function cipherRaw(): string
    {
        /** @var string|false $raw */
        $raw = base64_decode($this->cipherB64, true);
        if ($raw === false) {
            throw new RuntimeException('Invalid cipher b64');
        }
        return $raw;
    }
}
