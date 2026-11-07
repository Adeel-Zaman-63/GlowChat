-- ============================================================
-- GLOWCHAT: MESSAGE DELIVERY AND READ RECEIPTS
-- Migration: 006
-- Purpose: Persist per-recipient delivery and read timestamps
-- ============================================================

-- ------------------------------------------------------------
-- 1. MESSAGE RECEIPTS
-- ------------------------------------------------------------

CREATE TABLE message_receipts (
    message_id BIGINT NOT NULL,

    recipient_id BIGINT NOT NULL,

    delivered_at TIMESTAMPTZ,

    read_at TIMESTAMPTZ,

    CONSTRAINT message_receipts_pkey
        PRIMARY KEY (message_id, recipient_id),

    CONSTRAINT message_receipts_read_requires_delivery
        CHECK (read_at IS NULL OR delivered_at IS NOT NULL),

    CONSTRAINT message_receipts_message_fkey
        FOREIGN KEY (message_id)
        REFERENCES messages(id)
        ON DELETE CASCADE,

    CONSTRAINT message_receipts_recipient_fkey
        FOREIGN KEY (recipient_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_message_receipts_recipient
    ON message_receipts (recipient_id, message_id);

-- ------------------------------------------------------------
-- 2. BACKFILL EXISTING MESSAGE RECIPIENTS
-- ------------------------------------------------------------

INSERT INTO message_receipts (
    message_id,
    recipient_id
)
SELECT
    m.id,
    cp.user_id
FROM messages m
JOIN conversation_participants cp
    ON cp.conversation_id = m.conversation_id
   AND cp.user_id <> m.sender_id
   AND cp.joined_at <= m.created_at
   AND (
       cp.left_at IS NULL
       OR cp.left_at > m.created_at
   )
ON CONFLICT (message_id, recipient_id) DO NOTHING;

-- ============================================================
-- END OF MIGRATION 006
-- ============================================================
