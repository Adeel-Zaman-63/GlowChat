<?php

declare(strict_types=1);

namespace GlowChat\Middleware;

use GlowChat\Services\ApiResponse;
use GlowChat\Services\AuthenticationException;
use GlowChat\Services\SessionAuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionAuthenticationService $authentication
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $authorization = trim(
            $request->getHeaderLine('Authorization')
        );

        if (
            preg_match(
                '/^Bearer ([A-Za-z0-9_-]{43})$/iD',
                $authorization,
                $matches
            ) !== 1
        ) {
            return ApiResponse::error(
                new Response(),
                'Authentication required.',
                401
            );
        }

        try {
            $session = $this->authentication->authenticate(
                $matches[1]
            );
        } catch (AuthenticationException) {
            return ApiResponse::error(
                new Response(),
                'Authentication required.',
                401
            );
        }

        return $handler->handle(
            $request->withAttribute('auth_session', $session)
        );
    }
}
