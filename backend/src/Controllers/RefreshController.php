<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Repositories\RefreshTokenRepository;
use GlowChat\Repositories\SessionRepository;
use GlowChat\Services\ApiResponse;
use GlowChat\Services\RefreshTokenException;
use GlowChat\Services\RefreshTokenService;
use GlowChat\Services\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RefreshController
{
    public function refresh(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return ApiResponse::error(
                $response,
                'A valid JSON request body is required.',
                400
            );
        }

        $refreshToken = $data['refresh_token'] ?? null;

        if (
            !is_string($refreshToken)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/', $refreshToken)
        ) {
            return ApiResponse::error(
                $response,
                'A valid refresh token is required.',
                422
            );
        }

        try {
            $pdo = Database::connect();

            $service = new RefreshTokenService(
                $pdo,
                new RefreshTokenRepository($pdo),
                new SessionRepository($pdo),
                new TokenService()
            );

            $session = $service->rotate($refreshToken);

            return ApiResponse::success(
                $response,
                ['session' => $session],
                'Tokens refreshed successfully.'
            );
        } catch (RefreshTokenException $exception) {
            return ApiResponse::error(
                $response,
                'Invalid or expired refresh token.',
                401
            );
        }
    }
}
