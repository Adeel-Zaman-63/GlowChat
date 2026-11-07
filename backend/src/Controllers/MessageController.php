<?php

declare(strict_types=1);

namespace GlowChat\Controllers;

use GlowChat\Config\Database;
use GlowChat\Repositories\MessageRepository;
use GlowChat\Services\ApiResponse;
use GlowChat\Services\MessageException;
use GlowChat\Services\MessageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class MessageController
{
    private const MAX_PAYLOAD_BYTES = 65536;


    public function list(
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

        $query = $request->getQueryParams();
        $rawLimit = $query['limit'] ?? '20';

        if (!is_string($rawLimit) && !is_int($rawLimit)) {
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
                'A valid message pagination cursor UUID is required.',
                422,
                ['before' => 'Provide a valid message public UUID.']
            );
        }

        try {
            $pdo = Database::connect();

            $service = new MessageService(
                $pdo,
                new MessageRepository($pdo)
            );

            $result = $service->listForUser(
                (int) $auth['user_id'],
                $conversationPublicId,
                (int) $limitString,
                $beforePublicId
            );

            return ApiResponse::success(
                $response,
                $result,
                'Messages retrieved successfully.'
            );
        } catch (MessageException $exception) {
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

    public function send(
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

        $clientMessageId = $data['client_message_id'] ?? null;

        if (
            !is_string($clientMessageId)
            || preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                $clientMessageId
            ) !== 1
        ) {
            return ApiResponse::error(
                $response,
                'A valid client_message_id UUID is required.',
                422,
                ['client_message_id' => 'Provide a valid UUID.']
            );
        }

        $encryptedPayload = $data['encrypted_payload'] ?? null;

        if (
            !is_string($encryptedPayload)
            || $encryptedPayload === ''
            || strlen($encryptedPayload) > 87384
        ) {
            return ApiResponse::error(
                $response,
                'A valid encrypted_payload is required.',
                422,
                ['encrypted_payload' => 'Provide non-empty Base64 ciphertext up to 64 KiB decoded.']
            );
        }

        $decodedPayload = base64_decode($encryptedPayload, true);

        if (
            $decodedPayload === false
            || $decodedPayload === ''
            || strlen($decodedPayload) > self::MAX_PAYLOAD_BYTES
            || base64_encode($decodedPayload) !== $encryptedPayload
        ) {
            return ApiResponse::error(
                $response,
                'The encrypted_payload must be canonical Base64 encoding of 1 to 65536 bytes.',
                422,
                ['encrypted_payload' => 'Invalid or oversized Base64 ciphertext.']
            );
        }

        try {
            $pdo = Database::connect();

            $service = new MessageService(
                $pdo,
                new MessageRepository($pdo)
            );

            $result = $service->send(
                (int) $auth['user_id'],
                $conversationPublicId,
                $clientMessageId,
                $encryptedPayload
            );

            return ApiResponse::success(
                $response,
                ['message' => $result['message']],
                $result['created']
                    ? 'Encrypted message accepted.'
                    : 'Message was already accepted.',
                $result['created'] ? 201 : 200
            );

        } catch (MessageException $exception) {
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

    public function acknowledgeDelivered(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return $this->acknowledge(
            $request,
            $response,
            $args,
            'delivered'
        );
    }

    public function acknowledgeRead(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return $this->acknowledge(
            $request,
            $response,
            $args,
            'read'
        );
    }

    private function acknowledge(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
        string $receiptType
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
        $messagePublicId = $args['messageId'] ?? null;

        $uuidPattern =
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

        if (
            !is_string($conversationPublicId)
            || preg_match($uuidPattern, $conversationPublicId) !== 1
        ) {
            return ApiResponse::error(
                $response,
                'A valid conversation UUID is required.',
                422,
                ['conversationId' => 'Provide a valid conversation public UUID.']
            );
        }

        if (
            !is_string($messagePublicId)
            || preg_match($uuidPattern, $messagePublicId) !== 1
        ) {
            return ApiResponse::error(
                $response,
                'A valid message UUID is required.',
                422,
                ['messageId' => 'Provide a valid message public UUID.']
            );
        }

        try {
            $pdo = Database::connect();

            $service = new MessageService(
                $pdo,
                new MessageRepository($pdo)
            );

            $result = $receiptType === 'read'
                ? $service->acknowledgeRead(
                    (int) $auth['user_id'],
                    $conversationPublicId,
                    $messagePublicId
                )
                : $service->acknowledgeDelivered(
                    (int) $auth['user_id'],
                    $conversationPublicId,
                    $messagePublicId
                );

            return ApiResponse::success(
                $response,
                ['receipt' => $result],
                $receiptType === 'read'
                    ? 'Message marked as read.'
                    : 'Message marked as delivered.'
            );
        } catch (MessageException $exception) {
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
