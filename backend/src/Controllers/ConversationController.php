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

    public function createGroup(
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

        $title = $data['title'] ?? null;

        if (
            !is_string($title)
            || trim($title) === ''
            || mb_strlen(trim($title), 'UTF-8') > 100
        ) {
            return ApiResponse::error(
                $response,
                'A valid group title is required.',
                422,
                ['title' => 'The title must contain between 1 and 100 characters.']
            );
        }

        $participantPublicIds = $data['participant_public_ids'] ?? null;

        if (
            !is_array($participantPublicIds)
            || !array_is_list($participantPublicIds)
            || count($participantPublicIds) < 1
            || count($participantPublicIds) > 99
        ) {
            return ApiResponse::error(
                $response,
                'A valid participant_public_ids list is required.',
                422,
                ['participant_public_ids' => 'Provide between 1 and 99 participant UUIDs.']
            );
        }

        $uuidPattern =
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

        foreach ($participantPublicIds as $index => $publicId) {
            if (
                !is_string($publicId)
                || preg_match($uuidPattern, $publicId) !== 1
            ) {
                return ApiResponse::error(
                    $response,
                    'One or more participant UUIDs are invalid.',
                    422,
                    [
                        'participant_public_ids' =>
                            "Entry {$index} must be a valid public UUID.",
                    ]
                );
            }
        }

        try {
            $pdo = Database::connect();

            $service = new ConversationService(
                $pdo,
                new ConversationRepository($pdo)
            );

            $conversation = $service->createGroup(
                (int) $auth['user_id'],
                trim($title),
                $participantPublicIds
            );

            return ApiResponse::success(
                $response,
                ['conversation' => $conversation],
                'Group conversation created successfully.',
                201
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

    public function addGroupParticipants(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $auth = $request->getAttribute('auth_session');

        if (!is_array($auth) || !isset($auth['user_id'])) {
            return ApiResponse::error(
                $response,
                'Authentication is required.',
                401
            );
        }

        $conversationPublicId = $args['conversationId'] ?? null;

        if (
            !is_string($conversationPublicId)
            || preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                $conversationPublicId
            ) !== 1
        ) {
            return ApiResponse::error(
                $response,
                'A valid conversation UUID is required.',
                422,
                ['conversationId' => 'Provide a valid conversation public UUID.']
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

        $participantPublicIds = $data['participant_public_ids'] ?? null;

        if (
            !is_array($participantPublicIds)
            || !array_is_list($participantPublicIds)
            || count($participantPublicIds) < 1
            || count($participantPublicIds) > 99
        ) {
            return ApiResponse::error(
                $response,
                'A valid participant_public_ids list is required.',
                422,
                ['participant_public_ids' => 'Provide between 1 and 99 participant UUIDs.']
            );
        }

        $uuidPattern =
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

        foreach ($participantPublicIds as $index => $publicId) {
            if (
                !is_string($publicId)
                || preg_match($uuidPattern, $publicId) !== 1
            ) {
                return ApiResponse::error(
                    $response,
                    'One or more participant UUIDs are invalid.',
                    422,
                    [
                        'participant_public_ids' =>
                            "Entry {$index} must be a valid public UUID.",
                    ]
                );
            }
        }

        try {
            $pdo = Database::connect();

            $service = new ConversationService(
                $pdo,
                new ConversationRepository($pdo)
            );

            $result = $service->addGroupParticipants(
                (int) $auth['user_id'],
                $conversationPublicId,
                $participantPublicIds
            );

            return ApiResponse::success(
                $response,
                $result,
                'Group participants added successfully.'
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
