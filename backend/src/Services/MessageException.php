<?php

declare(strict_types=1);

namespace GlowChat\Services;

use RuntimeException;

final class MessageException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 400,
        private readonly mixed $validationErrors = null
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errors(): mixed
    {
        return $this->validationErrors;
    }
}
