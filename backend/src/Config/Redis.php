<?php

declare(strict_types=1);

namespace GlowChat\Config;

use Redis as RedisClient;
use RuntimeException;

final class Redis
{
    private static ?RedisClient $connection = null;

    public static function connect(): RedisClient
    {
        if (self::$connection !== null && self::$connection->isConnected()) {
            return self::$connection;
        }

        $host = (string) AppConfig::get('REDIS_HOST', '127.0.0.1');
        $port = (int) AppConfig::get('REDIS_PORT', 6379);
        $password = (string) AppConfig::get('REDIS_PASSWORD', '');

        try {
            $redis = new RedisClient();

            if (!$redis->connect($host, $port, 2.5)) {
                throw new RuntimeException('Redis connection failed.');
            }

            if ($password !== '' && !$redis->auth($password)) {
                throw new RuntimeException('Redis authentication failed.');
            }

            self::$connection = $redis;

            return self::$connection;
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Unable to connect to Redis.',
                0,
                $exception
            );
        }
    }
}
