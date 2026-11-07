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
}
