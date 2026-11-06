<?php

declare(strict_types=1);

namespace GlowChat\Validators;

final class RegistrationValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $displayName = $data['display_name'] ?? null;

        if (
            !is_string($username)
            || !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)
        ) {
            $errors['username'] =
                'Username must contain 3–30 letters, numbers, or underscores.';
        }

        if (
            !is_string($email)
            || strlen($email) > 255
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (
            !is_string($password)
            || strlen($password) < 12
            || strlen($password) > 128
        ) {
            $errors['password'] =
                'Password must contain between 12 and 128 characters.';
        }

        if (
            !is_string($displayName)
            || trim($displayName) === ''
            || mb_strlen(trim($displayName), 'UTF-8') > 80
        ) {
            $errors['display_name'] =
                'Display name must contain between 1 and 80 characters.';
        }

        return $errors;
    }
}
