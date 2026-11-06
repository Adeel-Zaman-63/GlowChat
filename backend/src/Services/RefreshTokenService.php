<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\RefreshTokenRepository;
use GlowChat\Repositories\SessionRepository;
use PDO;
use Throwable;

final class RefreshTokenService
{
    private const ACCESS_TOKEN_LIFETIME_MINUTES = 15;

    private const REFRESH_TOKEN_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly PDO $pdo,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly SessionRepository $sessions,
        private readonly TokenService $tokens
    ) {
    }

    public function rotate(string $refreshToken): array
    {
        $this->pdo->beginTransaction();

        try {
            $storedToken = $this->refreshTokens->findByHashForUpdate(
                $this->tokens->hash($refreshToken)
            );

            if ($storedToken === null) {
                throw new RefreshTokenException();
            }

            $sessionId = (int) $storedToken['session_id'];

            if ($storedToken['used_at'] !== null) {
                $this->sessions->revoke($sessionId);
                $this->refreshTokens->revokeSessionTokens($sessionId);

                $this->pdo->commit();

                throw new RefreshTokenException();
            }

            if (
                $storedToken['revoked_at'] !== null
                || $storedToken['session_revoked_at'] !== null
                || $storedToken['account_status'] !== 'active'
            ) {
                throw new RefreshTokenException();
            }

            if (!$this->refreshTokens->markAsUsed(
                (int) $storedToken['id']
            )) {
                throw new RefreshTokenException();
            }

            $accessToken = $this->tokens->generate();
            $newRefreshToken = $this->tokens->generate();

            $accessExpiresAt = new \DateTimeImmutable(
                '+' . self::ACCESS_TOKEN_LIFETIME_MINUTES . ' minutes'
            );

            $refreshExpiresAt = new \DateTimeImmutable(
                '+' . self::REFRESH_TOKEN_LIFETIME_DAYS . ' days'
            );

            if (!$this->sessions->rotateAccessToken(
                $sessionId,
                $this->tokens->hash($accessToken),
                $accessExpiresAt
            )) {
                throw new RefreshTokenException();
            }

            $this->refreshTokens->create(
                $sessionId,
                $this->tokens->hash($newRefreshToken),
                $refreshExpiresAt
            );

            $this->pdo->commit();

            return [
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_at' => $accessExpiresAt->format('Y-m-d H:i:sP'),
                'refresh_expires_at' => $refreshExpiresAt->format('Y-m-d H:i:sP'),
                'session_id' => $storedToken['session_public_id'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($exception instanceof RefreshTokenException) {
                throw $exception;
            }

            throw $exception;
        }
    }
}
