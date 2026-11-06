<?php

declare(strict_types=1);

namespace GlowChat\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function usernameExists(string $username): bool
    {
        $statement = $this->pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM users
                WHERE LOWER(username) = LOWER(:username)
            )
        ");

        $statement->execute([
            'username' => $username,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM users
                WHERE LOWER(email) = LOWER(:email)
            )
        ");

        $statement->execute([
            'email' => $email,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function create(
        string $username,
        string $email,
        string $passwordHash,
        string $displayName
    ): array {
        $statement = $this->pdo->prepare("
            INSERT INTO users (
                username,
                email,
                password_hash,
                display_name
            )
            VALUES (
                :username,
                :email,
                :password_hash,
                :display_name
            )
            RETURNING
                public_id,
                username,
                email,
                display_name,
                account_status,
                created_at
        ");

        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }
}
