<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use GlowChat\Config\Database;
use GlowChat\Database\MigrationManager;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$pdo = Database::connect();

$manager = new MigrationManager($pdo);
$manager->createMigrationTable();

$migrationDirectory = dirname(__DIR__) . '/database/migrations';

$migrations = glob($migrationDirectory . '/*.sql');

if ($migrations === false) {
    throw new RuntimeException(
        'Unable to read migration directory.'
    );
}

sort($migrations);

echo "===== GLOWCHAT DATABASE MIGRATIONS =====\n";

foreach ($migrations as $file) {
    $migration = basename($file);

    if ($manager->hasRun($migration)) {
        echo "[SKIPPED] {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException(
            "Migration file is empty or unreadable: {$migration}"
        );
    }

    $manager->run(
        $migration,
        static function (PDO $pdo) use ($sql): void {
            $pdo->exec($sql);
        }
    );

    echo "[APPLIED] {$migration}\n";
}

echo "\nAll pending migrations processed.\n";
