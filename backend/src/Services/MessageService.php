<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\MessageRepository;
use PDO;
use Throwable;

final class MessageService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MessageRepository $messages
    ) {
    }

    public function listForUser(
        int $userId,
        string $conversationPublicId,
        int $limit,
        ?string $beforePublicId = null
    ): array {
        $conversationId = $this->messages->findActiveConversationId(
            $conversationPublicId,
            $userId
        );

        if ($conversationId === null) {
            throw new MessageException(
                'The conversation was not found or is unavailable.',
                404
            );
        }

        $cursor = null;

        if ($beforePublicId !== null) {
            $cursor = $this->messages->findMessageCursor(
                $conversationId,
                $beforePublicId
            );

            if ($cursor === null) {
                throw new MessageException(
                    'The pagination cursor was not found in this conversation.',
                    404
                );
            }
        }

        $items = $this->messages->listForConversation(
            $conversationId,
            $userId,
            $limit + 1,
            $cursor
        );

        $hasMore = count($items) > $limit;

        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;

        if ($hasMore && $items !== []) {
            $lastItem = $items[array_key_last($items)];
            $nextCursor = $lastItem['public_id'];
        }

        foreach ($items as &$item) {
            $receipts = json_decode(
                $item['receipts'] ?? '[]',
                true
            );

            $item['receipts'] = is_array($receipts) ? $receipts : [];
        }
        unset($item);

        return [
            'messages' => $items,
            'pagination' => [
                'limit' => $limit,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    public function send(
        int $senderId,
        string $conversationPublicId,
        string $clientMessageId,
        string $encryptedPayloadBase64
    ): array {
        try {
            $this->pdo->beginTransaction();

            $conversationId =
                $this->messages->findActiveConversationForUpdate(
                    $conversationPublicId,
                    $senderId
                );

            if ($conversationId === null) {
                throw new MessageException(
                    'The conversation was not found or is unavailable.',
                    404
                );
            }

            $result = $this->messages->insertOrFind(
                $conversationId,
                $senderId,
                $clientMessageId,
                $encryptedPayloadBase64
            );

            if (!$result['payload_matches']) {
                throw new MessageException(
                    'This client_message_id has already been used with a different payload.',
                    409
                );
            }

            if ($result['created']) {
                $this->messages->createPendingReceipts(
                    (int) $result['message']['id'],
                    $conversationId,
                    $senderId
                );
            }

            $this->pdo->commit();

            return [
                'message' => [
                    'public_id' => $result['message']['public_id'],
                    'client_message_id' => $clientMessageId,
                    'conversation_public_id' => $conversationPublicId,
                    'created_at' => $result['message']['created_at'],
                ],
                'created' => $result['created'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function acknowledgeDelivered(
        int $userId,
        string $conversationPublicId,
        string $messagePublicId
    ): array {
        return $this->acknowledge(
            $userId,
            $conversationPublicId,
            $messagePublicId,
            'delivered'
        );
    }

    public function acknowledgeRead(
        int $userId,
        string $conversationPublicId,
        string $messagePublicId
    ): array {
        return $this->acknowledge(
            $userId,
            $conversationPublicId,
            $messagePublicId,
            'read'
        );
    }

    private function acknowledge(
        int $userId,
        string $conversationPublicId,
        string $messagePublicId,
        string $receiptType
    ): array {
        try {
            $this->pdo->beginTransaction();

            $conversationId = $this->messages->findActiveConversationId(
                $conversationPublicId,
                $userId
            );

            if ($conversationId === null) {
                throw new MessageException(
                    'The conversation was not found or is unavailable.',
                    404
                );
            }

            $receipt = $this->messages->acknowledgeReceipt(
                $conversationId,
                $messagePublicId,
                $userId,
                $receiptType
            );

            if ($receipt === null) {
                throw new MessageException(
                    'The message receipt was not found or is unavailable.',
                    404
                );
            }

            $this->pdo->commit();

            return [
                'message_public_id' => $receipt['public_id'],
                'delivered_at' => $receipt['delivered_at'],
                'read_at' => $receipt['read_at'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
