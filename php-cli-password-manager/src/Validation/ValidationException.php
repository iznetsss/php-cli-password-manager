<?php
declare(strict_types=1);

namespace App\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(
        private readonly string $shortCode,
        string                  $message
    )
    {
        parent::__construct($message);
    }

    public function codeShort(): string
    {
        return $this->shortCode;
    }
}
