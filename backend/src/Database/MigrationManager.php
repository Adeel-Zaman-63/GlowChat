<?php

declare(strict_types=1);

namespace GlowChat\Database;

use PDO;
use RuntimeException;

final class MigrationManager
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function createMigrationTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function hasRun(string $migration): bool
    {
        $statement = $this->pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM migrations
                WHERE migration = :migration
            )
        ");

        $statement->execute([
            'migration' => $migration,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function run(string $migration, callable $callback): void
    {
        if ($this->hasRun($migration)) {
            return;
        }

        if (!$this->pdo->beginTransaction()) {
            throw new RuntimeException(
                'Unable to start migration transaction.'
            );
        }

        try {
            $callback($this->pdo);

            $statement = $this->pdo->prepare("
                INSERT INTO migrations (migration)
                VALUES (:migration)
            ");

            $statement->execute([
                'migration' => $migration,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
