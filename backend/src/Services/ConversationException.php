<?php

declare(strict_types=1);

namespace GlowChat\Services;

use RuntimeException;

final class ConversationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $validationErrors = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->validationErrors;
    }
}
