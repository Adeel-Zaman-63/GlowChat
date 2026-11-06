<?php

declare(strict_types=1);

namespace GlowChat\Repositories;

use PDO;

final class RefreshTokenRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(
        int $sessionId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt
    ): array {
        $statement = $this->pdo->prepare("
            INSERT INTO refresh_tokens (
                session_id,
                token_hash,
                expires_at
            )
            VALUES (
                :session_id,
                :token_hash,
                :expires_at
            )
            RETURNING
                id,
                session_id,
                expires_at,
                created_at
        ");

        $statement->execute([
            'session_id' => $sessionId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function findByHashForUpdate(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT
                rt.id,
                rt.session_id,
                rt.used_at,
                rt.revoked_at,
                rt.expires_at AS refresh_expires_at,
                s.revoked_at AS session_revoked_at,
                s.expires_at AS access_expires_at,
                s.user_id,
                s.public_id AS session_public_id,
                u.account_status
            FROM refresh_tokens rt
            INNER JOIN user_sessions s
                ON s.id = rt.session_id
            INNER JOIN users u
                ON u.id = s.user_id
            WHERE rt.token_hash = :token_hash
            FOR UPDATE OF rt, s
        ");

        $statement->execute([
            'token_hash' => $tokenHash,
        ]);

        $token = $statement->fetch(PDO::FETCH_ASSOC);

        return $token === false ? null : $token;
    }

    public function markAsUsed(int $tokenId): bool
    {
        $statement = $this->pdo->prepare("
            UPDATE refresh_tokens
            SET used_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND used_at IS NULL
              AND revoked_at IS NULL
              AND expires_at > CURRENT_TIMESTAMP
        ");

        $statement->execute([
            'id' => $tokenId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revokeSessionTokens(int $sessionId): void
    {
        $statement = $this->pdo->prepare("
            UPDATE refresh_tokens
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE session_id = :session_id
              AND revoked_at IS NULL
        ");

        $statement->execute([
            'session_id' => $sessionId,
        ]);
    }
}
