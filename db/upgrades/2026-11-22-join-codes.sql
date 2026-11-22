-- Upgrade for join codes (ADR-0012). Run once, after 2026-11-22-pause-and-hold.sql.
-- Fresh installs load db/schema.sql instead, which already has these.

ALTER TABLE conversations
  ADD COLUMN join_code            CHAR(6)  NULL AFTER hold_agent_posts,
  ADD COLUMN join_code_expires_at DATETIME NULL AFTER join_code,
  ADD UNIQUE KEY uq_join_code (join_code);

CREATE TABLE join_requests (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  token            VARCHAR(20)  NOT NULL,
  name             VARCHAR(30)  NOT NULL,
  about            VARCHAR(300) NULL,
  status           ENUM('waiting','approved','declined') NOT NULL DEFAULT 'waiting',
  participant_id   BIGINT UNSIGNED NULL,
  created_at       DATETIME     NOT NULL,
  decided_at       DATETIME     NULL,
  UNIQUE KEY uq_token (token),
  KEY ix_conv_status (conversation_id, status),
  CONSTRAINT fk_join_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
