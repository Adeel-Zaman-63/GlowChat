<?php

declare(strict_types=1);

namespace GlowChat\Validators;

final class LoginValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $identifier = $data['identifier'] ?? null;
        $password = $data['password'] ?? null;

        if (
            !is_string($identifier)
            || trim($identifier) === ''
            || strlen(trim($identifier)) > 255
        ) {
            $errors['identifier'] =
                'Please provide a valid username or email address.';
        }

        if (
            !is_string($password)
            || strlen($password) < 1
            || strlen($password) > 128
        ) {
            $errors['password'] =
                'Password is required and must not exceed 128 characters.';
        }

        return $errors;
    }
}
