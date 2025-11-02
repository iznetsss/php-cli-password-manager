<?php
declare(strict_types=1);

namespace App\Vault\Model;

final readonly class CredentialInput
{
    public function __construct(
        public string $service,
        public string $username,
        public string $password,
        public string $note
    )
    {
    }
}
