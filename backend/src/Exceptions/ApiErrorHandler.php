<?php

declare(strict_types=1);

namespace GlowChat\Exceptions;

use GlowChat\Config\Logger;
use GlowChat\Services\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

final class ApiErrorHandler implements ErrorHandlerInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        if ($logErrors) {
            Logger::get()->error(
                'API exception',
                [
                    'method' => $request->getMethod(),
                    'path' => $request->getUri()->getPath(),
                    'exception' => $exception,
                ]
            );
        }

        $status = $exception instanceof HttpException
            ? $exception->getCode()
            : 500;

        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        if ($exception instanceof HttpException || $displayErrorDetails) {
            $message = $exception->getMessage();
        } else {
            $message = 'An internal server error occurred';
        }

        return ApiResponse::error(
            new \Slim\Psr7\Response(),
            $message,
            $status
        );
    }
}
