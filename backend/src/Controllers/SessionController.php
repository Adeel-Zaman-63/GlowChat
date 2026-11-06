<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Repositories\RefreshTokenRepository;
use GlowChat\Repositories\SessionRepository;
use GlowChat\Services\ApiResponse;
use GlowChat\Services\SessionManagementService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SessionController
{
    public function list(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');

        if (!is_array($auth)) {
            return $this->unauthorized($response);
        }

        $service = $this->service();

        $sessions = $service->listSessions(
            (int) $auth['user_id'],
            (int) $auth['_session_id']
        );

        foreach ($sessions as &$session) {
            $session['is_current'] = in_array(
                $session['is_current'],
                [true, 1, '1', 't', 'true'],
                true
            );
        }
        unset($session);

        return ApiResponse::success(
            $response,
            ['sessions' => $sessions],
            'Active sessions retrieved successfully.'
        );
    }

    public function logout(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');

        if (!is_array($auth)) {
            return $this->unauthorized($response);
        }

        $revoked = $this->service()->logout(
            (int) $auth['user_id'],
            (int) $auth['_session_id']
        );

        if (!$revoked) {
            return $this->unauthorized($response);
        }

        return ApiResponse::success(
            $response,
            null,
            'Logged out successfully.'
        );
    }

    public function revoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');
        $publicSessionId = $args['sessionId'] ?? '';

        if (!is_array($auth)) {
            return $this->unauthorized($response);
        }

        if (
            !is_string($publicSessionId)
            || preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                $publicSessionId
            ) !== 1
        ) {
            return ApiResponse::error(
                $response,
                'A valid session ID is required.',
                422
            );
        }

        $revoked = $this->service()->revokeSession(
            (int) $auth['user_id'],
            $publicSessionId
        );

        if (!$revoked) {
            return ApiResponse::error(
                $response,
                'Session not found or already revoked.',
                404
            );
        }

        return ApiResponse::success(
            $response,
            null,
            'Session revoked successfully.'
        );
    }

    public function revokeOthers(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');

        if (!is_array($auth)) {
            return $this->unauthorized($response);
        }

        $count = $this->service()->revokeOtherSessions(
            (int) $auth['user_id'],
            (int) $auth['_session_id']
        );

        return ApiResponse::success(
            $response,
            ['revoked_sessions' => $count],
            'Other sessions revoked successfully.'
        );
    }

    private function service(): SessionManagementService
    {
        $pdo = Database::connect();

        return new SessionManagementService(
            $pdo,
            new SessionRepository($pdo),
            new RefreshTokenRepository($pdo)
        );
    }

    private function unauthorized(
        ResponseInterface $response
    ): ResponseInterface {
        return ApiResponse::error(
            $response,
            'Authentication required.',
            401
        );
    }
}
