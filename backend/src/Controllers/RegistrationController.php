<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Repositories\UserRepository;
use GlowChat\Services\ApiResponse;
use GlowChat\Services\RegistrationException;
use GlowChat\Services\RegistrationService;
use GlowChat\Validators\RegistrationValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class RegistrationController
{
    public function register(
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

        try {
            $repository = new UserRepository(Database::connect());

            $service = new RegistrationService(
                $repository,
                new RegistrationValidator()
            );

            $user = $service->register($data);

            return ApiResponse::success(
                $response,
                $user,
                'Account created successfully.',
                201
            );
        } catch (RegistrationException $exception) {
            $status = str_contains(
                strtolower($exception->getMessage()),
                'already'
            ) ? 409 : 422;

            return ApiResponse::error(
                $response,
                $exception->getMessage(),
                $status,
                $exception->errors()
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }
}
