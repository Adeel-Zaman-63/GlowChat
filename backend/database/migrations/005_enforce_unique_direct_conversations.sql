-- ============================================================
-- GLOWCHAT: UNIQUE DIRECT CONVERSATIONS
-- Migration: 005
-- ============================================================

ALTER TABLE conversations
    ADD COLUMN direct_user_one_id BIGINT,
    ADD COLUMN direct_user_two_id BIGINT;

ALTER TABLE conversations
    ADD CONSTRAINT conversations_direct_users_valid
    CHECK (
        (
            conversation_type = 'direct'
            AND direct_user_one_id IS NOT NULL
            AND direct_user_two_id IS NOT NULL
            AND direct_user_one_id < direct_user_two_id
        )
        OR
        (
            conversation_type = 'group'
            AND direct_user_one_id IS NULL
            AND direct_user_two_id IS NULL
        )
    );

ALTER TABLE conversations
    ADD CONSTRAINT conversations_direct_user_one_fkey
    FOREIGN KEY (direct_user_one_id)
    REFERENCES users(id)
    ON DELETE CASCADE;

ALTER TABLE conversations
    ADD CONSTRAINT conversations_direct_user_two_fkey
    FOREIGN KEY (direct_user_two_id)
    REFERENCES users(id)
    ON DELETE CASCADE;

CREATE UNIQUE INDEX idx_conversations_unique_direct_pair
    ON conversations (direct_user_one_id, direct_user_two_id)
    WHERE conversation_type = 'direct';

-- ============================================================
-- END OF MIGRATION 005
-- ============================================================
