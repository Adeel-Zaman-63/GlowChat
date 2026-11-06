<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Repositories\SessionRepository;
use GlowChat\Repositories\RefreshTokenRepository;
use GlowChat\Repositories\UserRepository;
use GlowChat\Services\ApiResponse;
use GlowChat\Services\AuthenticationException;
use GlowChat\Services\AuthenticationService;
use GlowChat\Services\SessionService;
use GlowChat\Services\TokenService;
use GlowChat\Validators\LoginValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController
{
    public function login(
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

        $validator = new LoginValidator();
        $errors = $validator->validate($data);

        if (!empty($errors)) {
            return ApiResponse::error(
                $response,
                'Invalid login request.',
                422,
                $errors
            );
        }

        try {
            $pdo = Database::connect();

            $userRepository = new UserRepository($pdo);

            $authentication = new AuthenticationService(
                $userRepository,
                $validator
            );

            $user = $authentication->authenticate($data);

            $sessionService = new SessionService(
                $pdo,
                new SessionRepository($pdo),
                new TokenService(),
                new RefreshTokenRepository($pdo)
            );

            $deviceId = isset($data['device_id'])
                && is_string($data['device_id'])
                ? mb_substr(trim($data['device_id']), 0, 100)
                : null;

            $deviceName = isset($data['device_name'])
                && is_string($data['device_name'])
                ? mb_substr(trim($data['device_name']), 0, 100)
                : null;

            $session = $sessionService->createSession(
                (int) $user['id'],
                $deviceId !== '' ? $deviceId : null,
                $deviceName !== '' ? $deviceName : null
            );

            $publicUser = [
                'public_id' => $user['public_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
                'created_at' => $user['created_at'],
            ];

            return ApiResponse::success(
                $response,
                [
                    'user' => $publicUser,
                    'session' => $session,
                ],
                'Login successful.'
            );
        } catch (AuthenticationException $exception) {
            return ApiResponse::error(
                $response,
                'Invalid username/email or password.',
                401
            );
        }
    }
}
