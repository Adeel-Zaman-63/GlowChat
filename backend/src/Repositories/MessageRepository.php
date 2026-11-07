<?php

declare(strict_types=1);

namespace GlowChat\Repositories;

use PDO;
use RuntimeException;

final class MessageRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findActiveConversationForUpdate(
        string $conversationPublicId,
        int $userId
    ): ?int {
        $statement = $this->pdo->prepare("
            SELECT c.id
            FROM conversations c
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.id
            WHERE c.public_id = :conversation_public_id
              AND cp.user_id = :user_id
              AND cp.left_at IS NULL
            LIMIT 1
            FOR UPDATE OF cp
        ");

        $statement->execute([
            'conversation_public_id' => $conversationPublicId,
            'user_id' => $userId,
        ]);

        $conversationId = $statement->fetchColumn();

        return $conversationId === false
            ? null
            : (int) $conversationId;
    }

    public function insertOrFind(
        int $conversationId,
        int $senderId,
        string $clientMessageId,
        string $encryptedPayloadBase64
    ): array {
        $statement = $this->pdo->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_id,
                client_message_id,
                encrypted_payload
            )
            VALUES (
                :conversation_id,
                :sender_id,
                :client_message_id,
                decode(:encrypted_payload, 'base64')
            )
            ON CONFLICT (
                conversation_id,
                sender_id,
                client_message_id
            )
            DO NOTHING
            RETURNING id, public_id, created_at
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'client_message_id' => $clientMessageId,
            'encrypted_payload' => $encryptedPayloadBase64,
        ]);

        $message = $statement->fetch(PDO::FETCH_ASSOC);

        if ($message !== false) {
            $this->updateConversationTimestamp($conversationId);

            return [
                'message' => $message,
                'created' => true,
                'payload_matches' => true,
            ];
        }

        $lookup = $this->pdo->prepare("
            SELECT
                id,
                public_id,
                created_at,
                (
                    encrypted_payload =
                    decode(:encrypted_payload, 'base64')
                ) AS payload_matches
            FROM messages
            WHERE conversation_id = :conversation_id
              AND sender_id = :sender_id
              AND client_message_id = :client_message_id
            LIMIT 1
        ");

        $lookup->execute([
            'encrypted_payload' => $encryptedPayloadBase64,
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'client_message_id' => $clientMessageId,
        ]);

        $existing = $lookup->fetch(PDO::FETCH_ASSOC);

        if ($existing === false) {
            throw new RuntimeException(
                'Unable to retrieve the existing message.'
            );
        }

        return [
            'message' => [
                'id' => $existing['id'],
                'public_id' => $existing['public_id'],
                'created_at' => $existing['created_at'],
            ],
            'created' => false,
            'payload_matches' => in_array(
                $existing['payload_matches'],
                [true, 1, '1', 't'],
                true
            ),
        ];
    }

    public function createPendingReceipts(
        int $messageId,
        int $conversationId,
        int $senderId
    ): void {
        $statement = $this->pdo->prepare("
            INSERT INTO message_receipts (
                message_id,
                recipient_id
            )
            SELECT
                :message_id,
                cp.user_id
            FROM conversation_participants cp
            WHERE cp.conversation_id = :conversation_id
              AND cp.user_id <> :sender_id
              AND cp.left_at IS NULL
            ON CONFLICT (message_id, recipient_id) DO NOTHING
        ");

        $statement->execute([
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
        ]);
    }

    public function findActiveConversationId(
        string $conversationPublicId,
        int $userId
    ): ?int {
        $statement = $this->pdo->prepare("
            SELECT c.id
            FROM conversations c
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.id
            WHERE c.public_id = :conversation_public_id
              AND cp.user_id = :user_id
              AND cp.left_at IS NULL
            LIMIT 1
        ");

        $statement->execute([
            'conversation_public_id' => $conversationPublicId,
            'user_id' => $userId,
        ]);

        $conversationId = $statement->fetchColumn();

        return $conversationId === false
            ? null
            : (int) $conversationId;
    }

    public function acknowledgeReceipt(
        int $conversationId,
        string $messagePublicId,
        int $recipientId,
        string $receiptType
    ): ?array {
        if ($receiptType === 'read') {
            $timestampUpdate = "
                delivered_at = COALESCE(delivered_at, CURRENT_TIMESTAMP),
                read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
            ";
        } else {
            $timestampUpdate = "
                delivered_at = COALESCE(delivered_at, CURRENT_TIMESTAMP)
            ";
        }

        $statement = $this->pdo->prepare("
            UPDATE message_receipts mr
            SET {$timestampUpdate}
            FROM messages m
            WHERE mr.message_id = m.id
              AND m.conversation_id = :conversation_id
              AND m.public_id = :message_public_id
              AND mr.recipient_id = :recipient_id
            RETURNING
                m.public_id,
                mr.delivered_at,
                mr.read_at
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'message_public_id' => $messagePublicId,
            'recipient_id' => $recipientId,
        ]);

        $receipt = $statement->fetch(PDO::FETCH_ASSOC);

        return $receipt === false ? null : $receipt;
    }

    public function findMessageCursor(
        int $conversationId,
        string $messagePublicId
    ): ?array {
        $statement = $this->pdo->prepare("
            SELECT id, created_at
            FROM messages
            WHERE conversation_id = :conversation_id
              AND public_id = :public_id
            LIMIT 1
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'public_id' => $messagePublicId,
        ]);

        $cursor = $statement->fetch(PDO::FETCH_ASSOC);

        return $cursor === false ? null : $cursor;
    }

    public function listForConversation(
        int $conversationId,
        int $viewerUserId,
        int $limit,
        ?array $cursor = null
    ): array {
        $cursorCondition = '';

        if ($cursor !== null) {
            $cursorCondition = "
                AND (m.created_at, m.id) <
                    (CAST(:cursor_created_at AS TIMESTAMPTZ), :cursor_id)
            ";
        }

        $statement = $this->pdo->prepare("
            SELECT
                m.public_id,
                m.client_message_id,
                c.public_id AS conversation_public_id,
                u.public_id AS sender_public_id,
                CASE
                    WHEN m.deleted_at IS NULL
                    THEN replace(encode(m.encrypted_payload, 'base64'), chr(10), '')
                    ELSE NULL
                END AS encrypted_payload,
                m.created_at,
                m.edited_at,
                m.deleted_at,
                COALESCE(
                    (
                        SELECT json_agg(
                            json_build_object(
                                'recipient_public_id', recipient.public_id,
                                'delivered_at', receipt.delivered_at,
                                'read_at', receipt.read_at
                            )
                            ORDER BY receipt.recipient_id
                        )
                        FROM message_receipts receipt
                        INNER JOIN users recipient
                            ON recipient.id = receipt.recipient_id
                        WHERE receipt.message_id = m.id
                          AND (
                              m.sender_id = :sender_viewer_user_id
                              OR receipt.recipient_id = :recipient_viewer_user_id
                          )
                    ),
                    '[]'::json
                ) AS receipts
            FROM messages m
            INNER JOIN conversations c
                ON c.id = m.conversation_id
            INNER JOIN users u
                ON u.id = m.sender_id
            WHERE m.conversation_id = :conversation_id
                {$cursorCondition}
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT :result_limit
        ");

        $statement->bindValue(
            ':conversation_id',
            $conversationId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':sender_viewer_user_id',
            $viewerUserId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':recipient_viewer_user_id',
            $viewerUserId,
            PDO::PARAM_INT
        );

        if ($cursor !== null) {
            $statement->bindValue(
                ':cursor_created_at',
                $cursor['created_at'],
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':cursor_id',
                (int) $cursor['id'],
                PDO::PARAM_INT
            );
        }

        $statement->bindValue(
            ':result_limit',
            $limit,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function updateConversationTimestamp(
        int $conversationId
    ): void {
        $statement = $this->pdo->prepare("
            UPDATE conversations
            SET updated_at = CURRENT_TIMESTAMP
            WHERE id = :conversation_id
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
        ]);
    }
}
