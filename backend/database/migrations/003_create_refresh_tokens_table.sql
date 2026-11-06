CREATE TABLE refresh_tokens (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    session_id BIGINT NOT NULL
        REFERENCES user_sessions(id)
        ON DELETE CASCADE,

    token_hash CHAR(64) NOT NULL UNIQUE,

    expires_at TIMESTAMPTZ NOT NULL,

    used_at TIMESTAMPTZ,

    revoked_at TIMESTAMPTZ,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT refresh_tokens_hash_valid
        CHECK (token_hash ~ '^[a-f0-9]{64}$'),

    CONSTRAINT refresh_tokens_expiry_valid
        CHECK (expires_at > created_at)
);

CREATE INDEX idx_refresh_tokens_session_id
    ON refresh_tokens (session_id);

CREATE INDEX idx_refresh_tokens_expires_at
    ON refresh_tokens (expires_at);
