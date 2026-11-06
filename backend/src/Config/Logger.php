<?php

declare(strict_types=1);

namespace GlowChat\Config;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

final class Logger
{
    private static ?MonologLogger $logger = null;

    public static function get(): MonologLogger
    {
        if (self::$logger !== null) {
            return self::$logger;
        }

        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';

        $levelName = strtolower(
            (string) AppConfig::get('LOG_LEVEL', 'debug')
        );

        $level = match ($levelName) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Debug,
        };

        $logger = new MonologLogger('glowchat');

        $logger->pushHandler(
            new StreamHandler($logFile, $level)
        );

        self::$logger = $logger;

        return self::$logger;
    }
}
