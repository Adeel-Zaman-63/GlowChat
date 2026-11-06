<?php

declare(strict_types=1);

namespace GlowChat\Services;

use RuntimeException;

final class RegistrationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $errors
    ) {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
