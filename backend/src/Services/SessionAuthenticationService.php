<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\SessionRepository;

final class SessionAuthenticationService
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly TokenService $tokens
    ) {
    }

    public function authenticate(string $accessToken): array
    {
        if (
            $accessToken === ''
            || strlen($accessToken) > 128
            || preg_match('/^[A-Za-z0-9_-]+$/', $accessToken) !== 1
        ) {
            throw new AuthenticationException();
        }

        $tokenHash = $this->tokens->hash($accessToken);

        $session = $this->sessions->findActiveByTokenHash(
            $tokenHash
        );

        if (
            $session === null
            || $session['account_status'] !== 'active'
        ) {
            throw new AuthenticationException();
        }

        $session['_session_id'] = (int) $session['id'];

        $this->sessions->updateLastUsed(
            $session['_session_id']
        );

        unset($session['id']);

        return $session;
    }
}
