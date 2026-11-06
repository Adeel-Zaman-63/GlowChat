<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\RefreshTokenRepository;
use GlowChat\Repositories\SessionRepository;
use PDO;
use Throwable;

final class SessionManagementService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SessionRepository $sessions,
        private readonly RefreshTokenRepository $refreshTokens
    ) {
    }

    public function listSessions(
        int $userId,
        int $currentSessionId
    ): array {
        return $this->sessions->listActiveForUser(
            $userId,
            $currentSessionId
        );
    }

    public function logout(
        int $userId,
        int $currentSessionId
    ): bool {
        return $this->transaction(function () use (
            $userId,
            $currentSessionId
        ): bool {
            $revoked = $this->sessions->revokeOwnedSession(
                $currentSessionId,
                $userId
            );

            if ($revoked) {
                $this->refreshTokens->revokeSessionTokens(
                    $currentSessionId
                );
            }

            return $revoked;
        });
    }

    public function revokeSession(
        int $userId,
        string $publicSessionId
    ): bool {
        return $this->transaction(function () use (
            $userId,
            $publicSessionId
        ): bool {
            $sessionId = $this->sessions->revokeOwnedByPublicId(
                $userId,
                $publicSessionId
            );

            if ($sessionId === null) {
                return false;
            }

            $this->refreshTokens->revokeSessionTokens(
                $sessionId
            );

            return true;
        });
    }

    public function revokeOtherSessions(
        int $userId,
        int $currentSessionId
    ): int {
        return $this->transaction(function () use (
            $userId,
            $currentSessionId
        ): int {
            $revokedSessionIds = $this->sessions->revokeOtherSessions(
                $userId,
                $currentSessionId
            );

            foreach ($revokedSessionIds as $sessionId) {
                $this->refreshTokens->revokeSessionTokens(
                    $sessionId
                );
            }

            return count($revokedSessionIds);
        });
    }

    private function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $operation();

            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
