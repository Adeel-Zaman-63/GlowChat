<?php

declare(strict_types=1);

namespace GlowChat\Repositories;

use PDO;
use RuntimeException;

final class ConversationRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findActiveUserIdByPublicId(string $publicId): ?int
    {
        $statement = $this->pdo->prepare("
            SELECT id
            FROM users
            WHERE public_id = :public_id
              AND account_status = 'active'
            LIMIT 1
        ");

        $statement->execute(['public_id' => $publicId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function findOrCreateDirect(
        int $currentUserId,
        int $otherUserId
    ): array {
        $firstUserId = min($currentUserId, $otherUserId);
        $secondUserId = max($currentUserId, $otherUserId);

        $statement = $this->pdo->prepare("
            INSERT INTO conversations (
                conversation_type,
                direct_user_one_id,
                direct_user_two_id,
                created_by
            )
            VALUES (
                'direct',
                :first_user_id,
                :second_user_id,
                :created_by
            )
            ON CONFLICT (
                direct_user_one_id,
                direct_user_two_id
            )
            WHERE conversation_type = 'direct'
            DO NOTHING
            RETURNING id
        ");

        $statement->execute([
            'first_user_id' => $firstUserId,
            'second_user_id' => $secondUserId,
            'created_by' => $currentUserId,
        ]);

        $conversationId = $statement->fetchColumn();
        $created = $conversationId !== false;

        if ($created) {
            $conversationId = (int) $conversationId;

            $participants = $this->pdo->prepare("
                INSERT INTO conversation_participants (
                    conversation_id,
                    user_id
                )
                VALUES
                    (:conversation_id_one, :first_user_id),
                    (:conversation_id_two, :second_user_id)
            ");

            $participants->execute([
                'conversation_id_one' => $conversationId,
                'conversation_id_two' => $conversationId,
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
            ]);
        } else {
            $lookup = $this->pdo->prepare("
                SELECT id
                FROM conversations
                WHERE conversation_type = 'direct'
                  AND direct_user_one_id = :first_user_id
                  AND direct_user_two_id = :second_user_id
                LIMIT 1
            ");

            $lookup->execute([
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
            ]);

            $conversationId = $lookup->fetchColumn();

            if ($conversationId === false) {
                throw new RuntimeException(
                    'Unable to retrieve the direct conversation.'
                );
            }

            $conversationId = (int) $conversationId;
        }

        return [
            'conversation' => $this->findById($conversationId),
            'created' => $created,
        ];
    }

    public function findActiveUserIdsByPublicIds(array $publicIds): array
    {
        if ($publicIds === []) {
            return [];
        }

        $uuidArray = '{' . implode(',', $publicIds) . '}';

        $statement = $this->pdo->prepare("
            SELECT id, public_id
            FROM users
            WHERE public_id = ANY(CAST(:public_ids AS UUID[]))
              AND account_status = 'active'
            ORDER BY id
            FOR SHARE
        ");

        $statement->execute(['public_ids' => $uuidArray]);

        $users = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $users[strtolower($user['public_id'])] = (int) $user['id'];
        }

        return $users;
    }

    public function createGroup(
        int $creatorUserId,
        string $title,
        array $participantUserIds
    ): array {
        $statement = $this->pdo->prepare("
            INSERT INTO conversations (
                conversation_type,
                title,
                created_by
            )
            VALUES (
                'group',
                :title,
                :created_by
            )
            RETURNING id
        ");

        $statement->execute([
            'title' => $title,
            'created_by' => $creatorUserId,
        ]);

        $conversationId = (int) $statement->fetchColumn();

        $participantStatement = $this->pdo->prepare("
            INSERT INTO conversation_participants (
                conversation_id,
                user_id,
                role
            )
            VALUES (
                :conversation_id,
                :user_id,
                :role
            )
        ");

        $participantStatement->execute([
            'conversation_id' => $conversationId,
            'user_id' => $creatorUserId,
            'role' => 'admin',
        ]);

        foreach ($participantUserIds as $participantUserId) {
            $participantStatement->execute([
                'conversation_id' => $conversationId,
                'user_id' => $participantUserId,
                'role' => 'member',
            ]);
        }

        return $this->findById($conversationId);
    }

    public function lockGroupForParticipantManagement(
        string $conversationPublicId,
        int $adminUserId
    ): ?array {
        $statement = $this->pdo->prepare("
            SELECT c.id, c.conversation_type, c.created_by, cp.role
            FROM conversations c
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.id
            WHERE c.public_id = :public_id
              AND cp.user_id = :admin_user_id
              AND cp.left_at IS NULL
            FOR UPDATE OF c
        ");

        $statement->execute([
            'public_id' => $conversationPublicId,
            'admin_user_id' => $adminUserId,
        ]);

        $conversation = $statement->fetch(PDO::FETCH_ASSOC);

        return $conversation === false ? null : $conversation;
    }

    public function findActiveParticipantUserIds(
        int $conversationId,
        array $userIds
    ): array {
        if ($userIds === []) {
            return [];
        }

        $idArray = '{' . implode(',', array_map('intval', $userIds)) . '}';

        $statement = $this->pdo->prepare("
            SELECT user_id
            FROM conversation_participants
            WHERE conversation_id = :conversation_id
              AND user_id = ANY(CAST(:user_ids AS BIGINT[]))
              AND left_at IS NULL
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'user_ids' => $idArray,
        ]);

        return array_map(
            'intval',
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function countActiveParticipants(int $conversationId): int
    {
        $statement = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM conversation_participants
            WHERE conversation_id = :conversation_id
              AND left_at IS NULL
        ");

        $statement->execute(['conversation_id' => $conversationId]);

        return (int) $statement->fetchColumn();
    }

    public function addGroupParticipants(
        int $conversationId,
        array $userIds
    ): void {
        $statement = $this->pdo->prepare("
            INSERT INTO conversation_participants (
                conversation_id,
                user_id,
                role
            )
            VALUES (
                :conversation_id,
                :user_id,
                'member'
            )
            ON CONFLICT (conversation_id, user_id)
            DO UPDATE SET
                role = 'member',
                joined_at = CURRENT_TIMESTAMP,
                left_at = NULL
            WHERE conversation_participants.left_at IS NOT NULL
        ");

        foreach ($userIds as $userId) {
            $statement->execute([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ]);
        }
    }

    public function findActiveParticipantForRoleChange(
        int $conversationId,
        string $participantPublicId
    ): ?array {
        $statement = $this->pdo->prepare("
            SELECT cp.user_id, cp.role
            FROM conversation_participants cp
            INNER JOIN users u
                ON u.id = cp.user_id
            WHERE cp.conversation_id = :conversation_id
              AND u.public_id = :participant_public_id
              AND u.account_status = 'active'
              AND cp.left_at IS NULL
            LIMIT 1
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'participant_public_id' => $participantPublicId,
        ]);

        $participant = $statement->fetch(PDO::FETCH_ASSOC);

        return $participant === false ? null : $participant;
    }

    public function updateGroupParticipantRole(
        int $conversationId,
        int $userId,
        string $role
    ): void {
        $statement = $this->pdo->prepare("
            UPDATE conversation_participants
            SET role = :role
            WHERE conversation_id = :conversation_id
              AND user_id = :user_id
              AND left_at IS NULL
        ");

        $statement->execute([
            'role' => $role,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'The group participant role could not be updated.'
            );
        }
    }

    public function findParticipantForRemoval(
        int $conversationId,
        string $participantPublicId
    ): ?array {
        $statement = $this->pdo->prepare("
            SELECT
                cp.user_id,
                cp.role,
                cp.left_at
            FROM conversation_participants cp
            INNER JOIN users u
                ON u.id = cp.user_id
            WHERE cp.conversation_id = :conversation_id
              AND u.public_id = :participant_public_id
            LIMIT 1
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'participant_public_id' => $participantPublicId,
        ]);

        $participant = $statement->fetch(PDO::FETCH_ASSOC);

        return $participant === false ? null : $participant;
    }

    public function removeGroupParticipant(
        int $conversationId,
        int $userId
    ): void {
        $statement = $this->pdo->prepare("
            UPDATE conversation_participants
            SET left_at = CURRENT_TIMESTAMP
            WHERE conversation_id = :conversation_id
              AND user_id = :user_id
              AND left_at IS NULL
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'The group participant could not be removed.'
            );
        }
    }

    public function countActiveGroupAdmins(int $conversationId): int
    {
        $statement = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM conversation_participants
            WHERE conversation_id = :conversation_id
              AND role = 'admin'
              AND left_at IS NULL
        ");

        $statement->execute(['conversation_id' => $conversationId]);

        return (int) $statement->fetchColumn();
    }

    public function touchConversation(int $conversationId): void
    {
        $statement = $this->pdo->prepare("
            UPDATE conversations
            SET updated_at = CURRENT_TIMESTAMP
            WHERE id = :conversation_id
        ");

        $statement->execute(['conversation_id' => $conversationId]);
    }

    public function findActiveCursorForUser(
        string $publicId,
        int $userId
    ): ?array {
        $statement = $this->pdo->prepare("
            SELECT c.id, c.updated_at
            FROM conversations c
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.id
            WHERE c.public_id = :public_id
              AND cp.user_id = :user_id
              AND cp.left_at IS NULL
            LIMIT 1
        ");

        $statement->execute([
            'public_id' => $publicId,
            'user_id' => $userId,
        ]);

        $cursor = $statement->fetch(PDO::FETCH_ASSOC);

        return $cursor === false ? null : $cursor;
    }

    public function listForUser(
        int $userId,
        int $limit,
        ?array $cursor = null
    ): array {
        $cursorCondition = '';

        $parameters = [
            'user_id' => $userId,
        ];

        if ($cursor !== null) {
            $cursorCondition = "
                AND (c.updated_at, c.id) <
                    (CAST(:cursor_updated_at AS TIMESTAMPTZ), :cursor_id)
            ";

            $parameters['cursor_updated_at'] = $cursor['updated_at'];
            $parameters['cursor_id'] = (int) $cursor['id'];
        }

        $statement = $this->pdo->prepare("
            SELECT
                c.public_id,
                c.conversation_type,
                c.title,
                c.created_at,
                c.updated_at,
                COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'public_id', u.public_id,
                            'username', u.username,
                            'display_name', u.display_name,
                            'role', cp.role,
                            'joined_at', cp.joined_at
                        )
                        ORDER BY cp.joined_at, u.username
                    ) FILTER (WHERE u.id IS NOT NULL),
                    '[]'::jsonb
                ) AS participants
            FROM conversations c
            INNER JOIN conversation_participants mine
                ON mine.conversation_id = c.id
               AND mine.user_id = :user_id
               AND mine.left_at IS NULL
            LEFT JOIN conversation_participants cp
                ON cp.conversation_id = c.id
               AND cp.left_at IS NULL
            LEFT JOIN users u
                ON u.id = cp.user_id
               AND u.account_status = 'active'
            WHERE 1 = 1
                {$cursorCondition}
            GROUP BY c.id
            ORDER BY c.updated_at DESC, c.id DESC
            LIMIT :result_limit
        ");

        $statement->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        if ($cursor !== null) {
            $statement->bindValue(
                ':cursor_updated_at',
                $cursor['updated_at'],
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

        $conversations = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($conversations as &$conversation) {
            $conversation['participants'] = json_decode(
                $conversation['participants'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        unset($conversation);

        return $conversations;
    }

    public function findById(int $conversationId): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                c.public_id,
                c.conversation_type,
                c.title,
                c.created_at,
                c.updated_at,
                COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'public_id', u.public_id,
                            'username', u.username,
                            'display_name', u.display_name,
                            'role', cp.role,
                            'joined_at', cp.joined_at
                        )
                        ORDER BY cp.joined_at, u.username
                    ) FILTER (WHERE u.id IS NOT NULL),
                    '[]'::jsonb
                ) AS participants
            FROM conversations c
            LEFT JOIN conversation_participants cp
                ON cp.conversation_id = c.id
               AND cp.left_at IS NULL
            LEFT JOIN users u
                ON u.id = cp.user_id
               AND u.account_status = 'active'
            WHERE c.id = :conversation_id
            GROUP BY c.id
        ");

        $statement->execute([
            'conversation_id' => $conversationId,
        ]);

        $conversation = $statement->fetch(PDO::FETCH_ASSOC);

        if ($conversation === false) {
            throw new RuntimeException(
                'Conversation could not be retrieved.'
            );
        }

        $participants = json_decode(
            $conversation['participants'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $conversation['participants'] = $participants;

        return $conversation;
    }
}
