UPDATE password_reset_tokens
SET used_at = NOW()
WHERE used_at IS NULL;

ALTER TABLE password_reset_tokens
    DROP INDEX token_hash,
    MODIFY COLUMN token_hash VARCHAR(255) NOT NULL,
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER used_at;
