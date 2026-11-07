<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Repositories\ConversationRepository;
use GlowChat\Services\ApiResponse;
use GlowChat\Services\ConversationException;
use GlowChat\Services\ConversationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ConversationController
{
    public function list(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');

        if (!is_array($auth) || !isset($auth['user_id'])) {
            return ApiResponse::error(
                $response,
                'Authentication is required.',
                401
            );
        }

        $query = $request->getQueryParams();

        $rawLimit = $query['limit'] ?? '20';

        if (
            !is_string($rawLimit)
            && !is_int($rawLimit)
        ) {
            return ApiResponse::error(
                $response,
                'Invalid pagination limit.',
                422
            );
        }

        $limitString = (string) $rawLimit;

        if (
            preg_match('/^[0-9]{1,3}$/D', $limitString) !== 1
            || (int) $limitString < 1
            || (int) $limitString > 100
        ) {
            return ApiResponse::error(
                $response,
                'The limit must be between 1 and 100.',
                422,
                ['limit' => 'Provide a value between 1 and 100.']
            );
        }

        $beforePublicId = $query['before'] ?? null;

        if (
            $beforePublicId !== null
            && (
                !is_string($beforePublicId)
                || preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                    $beforePublicId
                ) !== 1
            )
        ) {
            return ApiResponse::error(
                $response,
                'A valid pagination cursor UUID is required.',
                422,
                ['before' => 'Provide a valid conversation public UUID.']
            );
        }

        try {
            $pdo = Database::connect();

            $service = new ConversationService(
                $pdo,
                new ConversationRepository($pdo)
            );

            $result = $service->listForUser(
                (int) $auth['user_id'],
                (int) $limitString,
                $beforePublicId
            );

            return ApiResponse::success(
                $response,
                $result,
                'Conversations retrieved successfully.'
            );
        } catch (ConversationException $exception) {
            return ApiResponse::error(
                $response,
                $exception->getMessage(),
                $exception->status(),
                $exception->errors()
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    public function createDirect(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');

        if (!is_array($auth) || !isset($auth['user_id'])) {
            return ApiResponse::error(
                $response,
                'Authentication is required.',
                401
            );
        }

        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return ApiResponse::error(
                $response,
                'A valid JSON request body is required.',
                400
            );
        }

        $publicId = $data['user_public_id'] ?? null;

        if (
            !is_string($publicId)
            || preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                $publicId
            ) !== 1
        ) {
            return ApiResponse::error(
                $response,
                'A valid user_public_id UUID is required.',
                422,
                ['user_public_id' => 'Provide a valid public UUID.']
            );
        }

        try {
            $pdo = Database::connect();

            $service = new ConversationService(
                $pdo,
                new ConversationRepository($pdo)
            );

            $result = $service->startDirect(
                (int) $auth['user_id'],
                $publicId
            );

            return ApiResponse::success(
                $response,
                [
                    'conversation' => $result['conversation'],
                    'created' => $result['created'],
                ],
                $result['created']
                    ? 'Direct conversation created successfully.'
                    : 'Direct conversation already exists.',
                $result['created'] ? 201 : 200
            );
        } catch (ConversationException $exception) {
            return ApiResponse::error(
                $response,
                $exception->getMessage(),
                $exception->status(),
                $exception->errors()
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }
}
