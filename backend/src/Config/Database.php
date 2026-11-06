<?php

declare(strict_types=1);

namespace GlowChat\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = AppConfig::get('DB_HOST', '127.0.0.1');
        $port = AppConfig::get('DB_PORT', '5432');
        $database = AppConfig::get('DB_DATABASE');
        $username = AppConfig::get('DB_USERNAME');
        $password = AppConfig::get('DB_PASSWORD');

        if (!$database || !$username || $password === null) {
            throw new RuntimeException(
                'Database configuration is incomplete.'
            );
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        try {
            self::$connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Unable to connect to the database.',
                0,
                $exception
            );
        }

        return self::$connection;
    }
}
