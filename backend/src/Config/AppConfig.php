<?php

declare(strict_types=1);

namespace GlowChat\Config;

final class AppConfig
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    public static function appName(): string
    {
        return (string) self::get('APP_NAME', 'GlowChat');
    }

    public static function environment(): string
    {
        return (string) self::get('APP_ENV', 'production');
    }

    public static function debug(): bool
    {
        return filter_var(
            self::get('APP_DEBUG', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function timezone(): string
    {
        return (string) self::get('APP_TIMEZONE', 'UTC');
    }
}
