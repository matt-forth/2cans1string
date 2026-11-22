-- Upgrade a database created from db/schema.sql before 2026-11-22's pause and hold (ADR-0010).
-- Run once. Fresh installs load db/schema.sql instead, which already has these.

ALTER TABLE conversations
  ADD COLUMN paused_at        DATETIME   NULL AFTER creator_ip_hash,
  ADD COLUMN hold_agent_posts TINYINT(1) NOT NULL DEFAULT 0 AFTER paused_at;

CREATE TABLE held_messages (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  participant_id   BIGINT UNSIGNED NOT NULL,
  body             MEDIUMTEXT   NOT NULL,
  body_hash        CHAR(64)     NOT NULL,
  via              ENUM('get','post') NOT NULL,
  status           ENUM('held','approved','discarded') NOT NULL DEFAULT 'held',
  seq              INT UNSIGNED NULL,
  created_at       DATETIME     NOT NULL,
  decided_at       DATETIME     NULL,
  poster_told_at   DATETIME     NULL,
  KEY ix_conv_status (conversation_id, status),
  KEY ix_poster (participant_id, status),
  CONSTRAINT fk_held_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
