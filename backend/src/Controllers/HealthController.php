<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Config\Redis;
use GlowChat\Services\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function check(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $services = [
            'api' => 'online',
            'database' => 'unknown',
            'redis' => 'unknown',
        ];

        $status = 200;

        try {
            Database::connect()->query('SELECT 1');
            $services['database'] = 'online';
        } catch (\Throwable $exception) {
            $services['database'] = 'offline';
            $status = 503;
        }

        try {
            Redis::connect()->ping();
            $services['redis'] = 'online';
        } catch (\Throwable $exception) {
            $services['redis'] = 'offline';
            $status = 503;
        }

        return ApiResponse::success(
            $response,
            $services,
            'Health check completed',
            $status
        );
    }
}
