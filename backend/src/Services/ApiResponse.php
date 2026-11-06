<?php

declare(strict_types=1);

namespace GlowChat\Services;

use Psr\Http\Message\ResponseInterface;

final class ApiResponse
{
    public static function success(
        ResponseInterface $response,
        mixed $data = null,
        string $message = 'Request successful',
        int $status = 200
    ): ResponseInterface {
        return self::json($response, [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(
        ResponseInterface $response,
        string $message,
        int $status = 400,
        mixed $errors = null
    ): ResponseInterface {
        return self::json($response, [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    private static function json(
        ResponseInterface $response,
        array $payload,
        int $status
    ): ResponseInterface {
        $response->getBody()->write(
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
