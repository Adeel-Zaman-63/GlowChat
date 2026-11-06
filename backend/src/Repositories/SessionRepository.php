<?php

declare(strict_types=1);

namespace GlowChat\Repositories;

use PDO;

final class SessionRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(
        int $userId,
        string $tokenHash,
        ?string $deviceId,
        ?string $deviceName,
        \DateTimeImmutable $expiresAt
    ): array {
        $statement = $this->pdo->prepare("
            INSERT INTO user_sessions (
                user_id,
                token_hash,
                device_id,
                device_name,
                expires_at
            )
            VALUES (
                :user_id,
                :token_hash,
                :device_id,
                :device_name,
                :expires_at
            )
            RETURNING
                id,
                public_id,
                device_id,
                device_name,
                expires_at,
                created_at
        ");

        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT
                s.id,
                s.public_id AS session_public_id,
                s.user_id,
                s.expires_at,
                u.public_id AS user_public_id,
                u.username,
                u.email,
                u.display_name,
                u.account_status
            FROM user_sessions s
            INNER JOIN users u ON u.id = s.user_id
            WHERE s.token_hash = :token_hash
              AND s.revoked_at IS NULL
              AND s.expires_at > CURRENT_TIMESTAMP
            LIMIT 1
        ");

        $statement->execute([
            'token_hash' => $tokenHash,
        ]);

        $session = $statement->fetch(PDO::FETCH_ASSOC);

        return $session === false ? null : $session;
    }

    public function updateLastUsed(int $sessionId): void
    {
        $statement = $this->pdo->prepare("
            UPDATE user_sessions
            SET last_used_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND revoked_at IS NULL
              AND expires_at > CURRENT_TIMESTAMP
        ");

        $statement->execute([
            'id' => $sessionId,
        ]);
    }

    public function rotateAccessToken(
        int $sessionId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt
    ): bool {
        $statement = $this->pdo->prepare("
            UPDATE user_sessions
            SET
                token_hash = :token_hash,
                expires_at = :expires_at,
                last_used_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND revoked_at IS NULL
        ");

        $statement->execute([
            'id' => $sessionId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
        ]);

        return $statement->rowCount() === 1;
    }

    public function revoke(int $sessionId): bool
    {
        $statement = $this->pdo->prepare("
            UPDATE user_sessions
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND revoked_at IS NULL
        ");

        $statement->execute([
            'id' => $sessionId,
        ]);

        return $statement->rowCount() > 0;
    }
}
