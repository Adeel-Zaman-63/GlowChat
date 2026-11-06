<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\RefreshTokenRepository;
use GlowChat\Repositories\SessionRepository;
use PDO;

final class SessionService
{
    private const ACCESS_TOKEN_LIFETIME_MINUTES = 15;

    private const REFRESH_TOKEN_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly PDO $pdo,
        private readonly SessionRepository $sessions,
        private readonly TokenService $tokens,
        private readonly RefreshTokenRepository $refreshTokens
    ) {
    }

    public function createSession(
        int $userId,
        ?string $deviceId = null,
        ?string $deviceName = null
    ): array {
        $accessToken = $this->tokens->generate();
        $refreshToken = $this->tokens->generate();

        $accessExpiresAt = new \DateTimeImmutable(
            '+' . self::ACCESS_TOKEN_LIFETIME_MINUTES . ' minutes'
        );

        $refreshExpiresAt = new \DateTimeImmutable(
            '+' . self::REFRESH_TOKEN_LIFETIME_DAYS . ' days'
        );

        $this->pdo->beginTransaction();

        try {
            $session = $this->sessions->create(
                $userId,
                $this->tokens->hash($accessToken),
                $deviceId,
                $deviceName,
                $accessExpiresAt
            );

            $this->refreshTokens->create(
                (int) $session['id'],
                $this->tokens->hash($refreshToken),
                $refreshExpiresAt
            );

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $session['expires_at'],
            'refresh_expires_at' => $refreshExpiresAt->format('Y-m-d H:i:sP'),
            'session_id' => $session['public_id'],
        ];
    }
}
