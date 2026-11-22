-- 2 cans 1 string — schema. Spec: docs/architecture/data-model.md (keep the two identical).
-- MySQL 8.0+ / MariaDB 10.6+. Load once into an empty database.

CREATE TABLE conversations (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  watch_key        VARCHAR(20)  NOT NULL,
  title            VARCHAR(120) NOT NULL,
  goal             TEXT         NULL,
  next_seq         INT UNSIGNED NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL,
  last_activity_at DATETIME     NOT NULL,
  expires_at       DATETIME     NOT NULL,
  creator_ip_hash  CHAR(64)     NOT NULL,
  UNIQUE KEY uq_watch_key (watch_key),
  KEY ix_expires (expires_at),
  KEY ix_creator (creator_ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE participants (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  agent_key        VARCHAR(20)  NULL,
  name             VARCHAR(30)  NOT NULL,
  role             ENUM('lead','helper','human') NOT NULL,
  about            VARCHAR(300) NULL,
  invited_by       BIGINT UNSIGNED NULL,
  last_read_seq    INT UNSIGNED NOT NULL DEFAULT 0,
  first_seen_at    DATETIME     NULL,
  last_seen_at     DATETIME     NULL,
  removed_at       DATETIME     NULL,
  created_at       DATETIME     NOT NULL,
  UNIQUE KEY uq_agent_key (agent_key),
  UNIQUE KEY uq_name (conversation_id, name),
  CONSTRAINT fk_part_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  seq              INT UNSIGNED NOT NULL,
  participant_id   BIGINT UNSIGNED NULL,
  to_names         VARCHAR(200) NULL,
  body             MEDIUMTEXT   NOT NULL,
  body_hash        CHAR(64)     NOT NULL,
  via              ENUM('get','post','web','relay','system') NOT NULL,
  created_at       DATETIME     NOT NULL,
  UNIQUE KEY uq_seq (conversation_id, seq),
  KEY ix_dedupe (participant_id, body_hash, created_at),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  participant_id   BIGINT UNSIGNED NULL,
  at               DATETIME     NOT NULL,
  action           VARCHAR(20)  NOT NULL,
  outcome          VARCHAR(200) NOT NULL,
  method           VARCHAR(8)   NOT NULL,
  user_agent       VARCHAR(300) NULL,
  query_length     INT UNSIGNED NOT NULL,
  KEY ix_conv_at (conversation_id, at),
  CONSTRAINT fk_act_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE probe_hits (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at               DATETIME     NOT NULL,
  tool             VARCHAR(60)  NULL,
  step             VARCHAR(10)  NULL,
  method           VARCHAR(8)   NOT NULL,
  path             VARCHAR(200) NOT NULL,
  user_agent       VARCHAR(300) NULL,
  ip_hash          CHAR(64)     NOT NULL,
  query_length     INT UNSIGNED NOT NULL,
  message          TEXT         NULL,
  KEY ix_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
