<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\UserRepository;
use GlowChat\Validators\RegistrationValidator;
use RuntimeException;

final class RegistrationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RegistrationValidator $validator
    ) {
    }

    public function register(array $data): array
    {
        $errors = $this->validator->validate($data);

        if (!empty($errors)) {
            throw new RegistrationException(
                'Registration validation failed.',
                $errors
            );
        }

        $username = trim($data['username']);
        $email = trim($data['email']);
        $password = $data['password'];
        $displayName = trim($data['display_name']);

        if ($this->users->usernameExists($username)) {
            throw new RegistrationException(
                'Username is already taken.',
                ['username' => 'This username is unavailable.']
            );
        }

        if ($this->users->emailExists($email)) {
            throw new RegistrationException(
                'Email address is already registered.',
                ['email' => 'This email address is unavailable.']
            );
        }

        $passwordHash = password_hash(
            $password,
            PASSWORD_ARGON2ID
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'Unable to securely hash the password.'
            );
        }

        try {
            return $this->users->create(
                $username,
                $email,
                $passwordHash,
                $displayName
            );
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23505') {
                throw new RegistrationException(
                    'Username or email address is already registered.',
                    ['account' => 'Username or email address is unavailable.']
                );
            }

            throw $exception;
        }
    }
}
