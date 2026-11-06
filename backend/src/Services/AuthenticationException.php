<?php

declare(strict_types=1);

namespace GlowChat\Services;

use RuntimeException;

final class AuthenticationException extends RuntimeException
{
    public function __construct(
        string $message = 'Invalid username/email or password.'
    ) {
        parent::__construct($message);
    }
}
