<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\UserRepository;
use GlowChat\Validators\LoginValidator;

final class AuthenticationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginValidator $validator
    ) {
    }

    public function authenticate(array $data): array
    {
        $errors = $this->validator->validate($data);

        if (!empty($errors)) {
            throw new AuthenticationException(
                'Invalid login request.'
            );
        }

        $identifier = trim($data['identifier']);
        $password = $data['password'];

        $user = $this->users->findForAuthentication($identifier);

        if (
            $user === null
            || !password_verify($password, $user['password_hash'])
        ) {
            throw new AuthenticationException();
        }

        if ($user['account_status'] !== 'active') {
            throw new AuthenticationException(
                'This account is currently unavailable.'
            );
        }

        unset($user['password_hash']);

        return $user;
    }
}
