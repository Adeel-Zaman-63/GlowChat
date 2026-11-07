-- ============================================================
-- GLOWCHAT: MESSAGING DATABASE FOUNDATION
-- Migration: 004
-- Purpose: Conversations, participants, and encrypted messages
-- ============================================================

-- ------------------------------------------------------------
-- 1. CONVERSATIONS
-- ------------------------------------------------------------

CREATE TABLE conversations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    public_id UUID NOT NULL DEFAULT gen_random_uuid(),

    conversation_type VARCHAR(20) NOT NULL,

    title VARCHAR(100),

    created_by BIGINT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT conversations_public_id_unique
        UNIQUE (public_id),

    CONSTRAINT conversations_type_valid
        CHECK (conversation_type IN ('direct', 'group')),

    CONSTRAINT conversations_title_not_empty
        CHECK (title IS NULL OR LENGTH(TRIM(title)) > 0),

    CONSTRAINT conversations_direct_title_valid
        CHECK (conversation_type <> 'direct' OR title IS NULL),

    CONSTRAINT conversations_created_by_fkey
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_conversations_created_by
    ON conversations (created_by);

CREATE INDEX idx_conversations_created_at
    ON conversations (created_at DESC);


-- ------------------------------------------------------------
-- 2. CONVERSATION PARTICIPANTS
-- ------------------------------------------------------------

CREATE TABLE conversation_participants (
    conversation_id BIGINT NOT NULL,

    user_id BIGINT NOT NULL,

    role VARCHAR(20) NOT NULL DEFAULT 'member',

    joined_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    left_at TIMESTAMPTZ,

    CONSTRAINT conversation_participants_pkey
        PRIMARY KEY (conversation_id, user_id),

    CONSTRAINT conversation_participants_role_valid
        CHECK (role IN ('member', 'admin')),

    CONSTRAINT conversation_participants_dates_valid
        CHECK (left_at IS NULL OR left_at >= joined_at),

    CONSTRAINT conversation_participants_conversation_fkey
        FOREIGN KEY (conversation_id)
        REFERENCES conversations(id)
        ON DELETE CASCADE,

    CONSTRAINT conversation_participants_user_fkey
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_conversation_participants_user_active
    ON conversation_participants (user_id, conversation_id)
    WHERE left_at IS NULL;

CREATE INDEX idx_conversation_participants_conversation_active
    ON conversation_participants (conversation_id, user_id)
    WHERE left_at IS NULL;


-- ------------------------------------------------------------
-- 3. MESSAGES
-- ------------------------------------------------------------

CREATE TABLE messages (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    public_id UUID NOT NULL DEFAULT gen_random_uuid(),

    conversation_id BIGINT NOT NULL,

    sender_id BIGINT NOT NULL,

    client_message_id UUID NOT NULL,

    encrypted_payload BYTEA NOT NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    edited_at TIMESTAMPTZ,

    deleted_at TIMESTAMPTZ,

    CONSTRAINT messages_public_id_unique
        UNIQUE (public_id),

    CONSTRAINT messages_client_id_unique
        UNIQUE (conversation_id, sender_id, client_message_id),

    CONSTRAINT messages_payload_not_empty
        CHECK (OCTET_LENGTH(encrypted_payload) > 0),

    CONSTRAINT messages_edited_at_valid
        CHECK (edited_at IS NULL OR edited_at >= created_at),

    CONSTRAINT messages_deleted_at_valid
        CHECK (deleted_at IS NULL OR deleted_at >= created_at),

    CONSTRAINT messages_conversation_fkey
        FOREIGN KEY (conversation_id)
        REFERENCES conversations(id)
        ON DELETE CASCADE,

    CONSTRAINT messages_sender_fkey
        FOREIGN KEY (sender_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

CREATE INDEX idx_messages_conversation_timeline
    ON messages (conversation_id, created_at DESC, id DESC);

CREATE INDEX idx_messages_sender_created_at
    ON messages (sender_id, created_at DESC);

-- ============================================================
-- END OF MIGRATION 004
-- ============================================================
