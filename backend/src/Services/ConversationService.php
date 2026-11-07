<?php

declare(strict_types=1);

namespace GlowChat\Services;

use GlowChat\Repositories\ConversationRepository;
use PDO;
use Throwable;

final class ConversationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ConversationRepository $conversations
    ) {
    }

    public function listForUser(
        int $userId,
        int $limit,
        ?string $beforePublicId = null
    ): array {
        $cursor = null;

        if ($beforePublicId !== null) {
            $cursor = $this->conversations->findActiveCursorForUser(
                $beforePublicId,
                $userId
            );

            if ($cursor === null) {
                throw new ConversationException(
                    'The pagination cursor was not found.',
                    404
                );
            }
        }

        $items = $this->conversations->listForUser(
            $userId,
            $limit,
            $cursor
        );

        $nextCursor = null;

        if (count($items) === $limit) {
            $lastItem = $items[array_key_last($items)];
            $nextCursor = $lastItem['public_id'];
        }

        return [
            'conversations' => $items,
            'pagination' => [
                'limit' => $limit,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    public function startDirect(
        int $currentUserId,
        string $otherUserPublicId
    ): array {
        $otherUserId = $this->conversations
            ->findActiveUserIdByPublicId($otherUserPublicId);

        if ($otherUserId === null) {
            throw new ConversationException(
                'The requested user was not found or is unavailable.',
                404
            );
        }

        if ($otherUserId === $currentUserId) {
            throw new ConversationException(
                'You cannot create a direct conversation with yourself.',
                422,
                ['user_public_id' => 'Choose another user.']
            );
        }

        try {
            $this->pdo->beginTransaction();

            $result = $this->conversations->findOrCreateDirect(
                $currentUserId,
                $otherUserId
            );

            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function createGroup(
        int $creatorUserId,
        string $title,
        array $participantPublicIds
    ): array {
        if (count($participantPublicIds) !== count(array_unique(
            array_map('strtolower', $participantPublicIds)
        ))) {
            throw new ConversationException(
                'Duplicate group participants are not allowed.',
                422,
                ['participant_public_ids' => 'Remove duplicate UUIDs.']
            );
        }

        try {
            $this->pdo->beginTransaction();

            $usersByPublicId = $this->conversations
                ->findActiveUserIdsByPublicIds($participantPublicIds);

            if (count($usersByPublicId) !== count($participantPublicIds)) {
                throw new ConversationException(
                    'One or more participants were not found or are unavailable.',
                    404,
                    ['participant_public_ids' => 'Use UUIDs belonging to active accounts.']
                );
            }

            $participantUserIds = [];

            foreach ($participantPublicIds as $publicId) {
                $userId = $usersByPublicId[strtolower($publicId)];

                if ($userId === $creatorUserId) {
                    throw new ConversationException(
                        'The group creator must not be included in the participant list.',
                        422,
                        ['participant_public_ids' => 'The creator is added automatically.']
                    );
                }

                $participantUserIds[] = $userId;
            }

            $conversation = $this->conversations->createGroup(
                $creatorUserId,
                $title,
                $participantUserIds
            );

            $this->pdo->commit();

            return $conversation;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function addGroupParticipants(
        int $adminUserId,
        string $conversationPublicId,
        array $participantPublicIds
    ): array {
        if (count($participantPublicIds) !== count(array_unique(
            array_map('strtolower', $participantPublicIds)
        ))) {
            throw new ConversationException(
                'Duplicate group participants are not allowed.',
                422,
                ['participant_public_ids' => 'Remove duplicate UUIDs.']
            );
        }

        try {
            $this->pdo->beginTransaction();

            $conversation = $this->conversations
                ->lockGroupForParticipantManagement(
                    $conversationPublicId,
                    $adminUserId
                );

            if ($conversation === null) {
                throw new ConversationException(
                    'The conversation was not found or you are not an active participant.',
                    404
                );
            }

            if ($conversation['conversation_type'] !== 'group') {
                throw new ConversationException(
                    'Participants can only be added to group conversations.',
                    422
                );
            }

            if ($conversation['role'] !== 'admin') {
                throw new ConversationException(
                    'Only group admins can add participants.',
                    403
                );
            }

            $conversationId = (int) $conversation['id'];

            $usersByPublicId = $this->conversations
                ->findActiveUserIdsByPublicIds($participantPublicIds);

            if (count($usersByPublicId) !== count($participantPublicIds)) {
                throw new ConversationException(
                    'One or more users were not found or are unavailable.',
                    404,
                    ['participant_public_ids' => 'Use UUIDs belonging to active accounts.']
                );
            }

            $participantUserIds = [];

            foreach ($participantPublicIds as $publicId) {
                $participantUserIds[] = $usersByPublicId[strtolower($publicId)];
            }

            $alreadyActive = $this->conversations
                ->findActiveParticipantUserIds(
                    $conversationId,
                    $participantUserIds
                );

            if ($alreadyActive !== []) {
                throw new ConversationException(
                    'One or more users are already active participants.',
                    409,
                    ['participant_public_ids' => 'Remove users who are already in the group.']
                );
            }

            $currentCount = $this->conversations
                ->countActiveParticipants($conversationId);

            if ($currentCount + count($participantUserIds) > 100) {
                throw new ConversationException(
                    'The group cannot contain more than 100 active participants.',
                    422,
                    ['participant_public_ids' => 'The group has insufficient remaining capacity.']
                );
            }

            $this->conversations->addGroupParticipants(
                $conversationId,
                $participantUserIds
            );

            $this->conversations->touchConversation($conversationId);

            $result = [
                'conversation' => $this->conversations->findById($conversationId),
                'added_count' => count($participantUserIds),
            ];

            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }


    public function removeGroupParticipant(
        int $adminUserId,
        string $conversationPublicId,
        string $participantPublicId
    ): array {
        try {
            $this->pdo->beginTransaction();

            $conversation = $this->conversations
                ->lockGroupForParticipantManagement(
                    $conversationPublicId,
                    $adminUserId
                );

            if ($conversation === null) {
                throw new ConversationException(
                    'The conversation was not found or you are not an active participant.',
                    404
                );
            }

            if ($conversation['conversation_type'] !== 'group') {
                throw new ConversationException(
                    'Participants can only be removed from group conversations.',
                    422
                );
            }

            if ($conversation['role'] !== 'admin') {
                throw new ConversationException(
                    'Only group admins can remove participants.',
                    403
                );
            }

            $conversationId = (int) $conversation['id'];

            $participant = $this->conversations
                ->findParticipantForRemoval(
                    $conversationId,
                    $participantPublicId
                );

            if ($participant === null) {
                throw new ConversationException(
                    'The participant was not found in this conversation.',
                    404
                );
            }

            if ($participant['left_at'] !== null) {
                throw new ConversationException(
                    'The participant has already left this conversation.',
                    409
                );
            }

            $participantUserId = (int) $participant['user_id'];

            if ($participantUserId === $adminUserId) {
                throw new ConversationException(
                    'You cannot remove yourself using the admin removal endpoint.',
                    422
                );
            }

            if (
                $conversation['created_by'] !== null
                && $participantUserId === (int) $conversation['created_by']
            ) {
                throw new ConversationException(
                    'The group creator cannot be removed.',
                    403
                );
            }

            if ($participant['role'] === 'admin') {
                throw new ConversationException(
                    'Group admins cannot be removed through this endpoint.',
                    403
                );
            }

            $this->conversations->removeGroupParticipant(
                $conversationId,
                $participantUserId
            );

            $this->conversations->touchConversation($conversationId);

            $result = [
                'conversation' => $this->conversations->findById($conversationId),
                'removed_count' => 1,
            ];

            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }


    public function leaveGroupConversation(
        int $userId,
        string $conversationPublicId
    ): array {
        try {
            $this->pdo->beginTransaction();

            $conversation = $this->conversations
                ->lockGroupForParticipantManagement(
                    $conversationPublicId,
                    $userId
                );

            if ($conversation === null) {
                throw new ConversationException(
                    'The conversation was not found or you are not an active participant.',
                    404
                );
            }

            if ($conversation['conversation_type'] !== 'group') {
                throw new ConversationException(
                    'Only group participants can use this leave endpoint.',
                    422
                );
            }

            if (
                $conversation['created_by'] !== null
                && (int) $conversation['created_by'] === $userId
            ) {
                throw new ConversationException(
                    'The group creator cannot leave until ownership transfer is supported.',
                    403
                );
            }

            if ($conversation['role'] === 'admin') {
                $activeAdminCount = $this->conversations
                    ->countActiveGroupAdmins((int) $conversation['id']);

                if ($activeAdminCount <= 1) {
                    throw new ConversationException(
                        'You cannot leave because the group must retain an active admin.',
                        409
                    );
                }
            }

            $conversationId = (int) $conversation['id'];

            $this->conversations->removeGroupParticipant(
                $conversationId,
                $userId
            );

            $this->conversations->touchConversation($conversationId);

            $result = [
                'conversation' => $this->conversations->findById($conversationId),
                'left_count' => 1,
            ];

            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

}
