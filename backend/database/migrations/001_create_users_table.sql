CREATE TABLE users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    public_id UUID NOT NULL UNIQUE DEFAULT gen_random_uuid(),

    username VARCHAR(30) NOT NULL,
    email VARCHAR(255) NOT NULL,

    password_hash VARCHAR(255) NOT NULL,

    display_name VARCHAR(80) NOT NULL,

    profile_picture_path TEXT,

    account_status VARCHAR(20) NOT NULL DEFAULT 'active',

    email_verified_at TIMESTAMPTZ,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT users_username_format
        CHECK (username ~ '^[a-zA-Z0-9_]{3,30}$'),

    CONSTRAINT users_email_not_empty
        CHECK (length(trim(email)) > 0),

    CONSTRAINT users_display_name_not_empty
        CHECK (length(trim(display_name)) > 0),

    CONSTRAINT users_password_hash_not_empty
        CHECK (length(password_hash) > 0),

    CONSTRAINT users_account_status_valid
        CHECK (
            account_status IN (
                'active',
                'suspended',
                'deactivated'
            )
        )
);

CREATE UNIQUE INDEX idx_users_username_lower
    ON users (LOWER(username));

CREATE UNIQUE INDEX idx_users_email_lower
    ON users (LOWER(email));

CREATE INDEX idx_users_account_status
    ON users (account_status);

CREATE INDEX idx_users_created_at
    ON users (created_at DESC);

CREATE FUNCTION update_users_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_users_updated_at();
