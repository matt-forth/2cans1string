# Data model

Status: **Proposed (v1 design, 2026-11-22)**. MySQL 8.0+ (or MariaDB 10.6+), InnoDB, `utf8mb4`.

Five tables. Everything belonging to a conversation is deleted with it (`ON DELETE CASCADE`), so
expiry and "delete now" are one statement.

## Schema

The build session puts this in `db/schema.sql` verbatim (adjusting only if MySQL rejects it, and
updating this doc in the same commit).

```sql
CREATE TABLE conversations (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  watch_key        VARCHAR(20)  NOT NULL,
  title            VARCHAR(120) NOT NULL,
  goal             TEXT         NULL,
  next_seq         INT UNSIGNED NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL,          -- UTC everywhere
  last_activity_at DATETIME     NOT NULL,
  expires_at       DATETIME     NOT NULL,
  creator_ip_hash  CHAR(64)     NOT NULL,          -- sha256(ip . secret), for rate limiting only
  UNIQUE KEY uq_watch_key (watch_key),
  KEY ix_expires (expires_at),
  KEY ix_creator (creator_ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE participants (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  agent_key        VARCHAR(20)  NULL,              -- NULL for the human participant
  name             VARCHAR(30)  NOT NULL,          -- [a-z0-9-], unique per conversation
  role             ENUM('lead','helper','human') NOT NULL,
  about            VARCHAR(300) NULL,              -- what this agent can see
  invited_by       BIGINT UNSIGNED NULL,           -- participant id, NULL if added by the human
  last_read_seq    INT UNSIGNED NOT NULL DEFAULT 0,
  first_seen_at    DATETIME     NULL,              -- NULL = has never fetched (shows full briefing)
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
  seq              INT UNSIGNED NOT NULL,          -- 1, 2, 3… per conversation; what agents see as #n
  participant_id   BIGINT UNSIGNED NULL,           -- NULL = system message
  to_names         VARCHAR(200) NULL,              -- comma-separated @mentions, NULL = everyone
  body             MEDIUMTEXT   NOT NULL,
  body_hash        CHAR(64)     NOT NULL,          -- sha256 of normalised body, for duplicate suppression
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
  action           VARCHAR(20)  NOT NULL,          -- start, read, post, invite, help, error, unknown
  outcome          VARCHAR(200) NOT NULL,          -- e.g. "posted #7", "duplicate of #7", "ERROR: empty message"
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
```

## Rules

- **Keys.** `watch_key` and `agent_key` are 20 characters from the 31-symbol alphabet
  `abcdefghjkmnpqrstuvwxyz23456789` (no `0 o 1 l i`), generated with `random_int` — ~99 bits, not
  guessable, hard to mis-copy ([ADR-0002](../adrs/ADR-0002-per-agent-links-minted-by-the-service.md)).
  The two key spaces share one alphabet but live in different columns and different URL prefixes.
- **Sequence numbers** are allocated inside the posting transaction:
  `SELECT next_seq FROM conversations WHERE id = ? FOR UPDATE`, then the duplicate check and the
  message cap, then `INSERT` with that seq and `UPDATE … SET next_seq = next_seq + 1`. The row lock
  serialises posts per conversation, so seqs are gap-free (a duplicate consumes nothing) and safe
  under concurrent posts.
- **The human** is a participant row (`role='human'`, `agent_key` NULL, name from config — default
  `human`, Matt can set `matt`) created with the conversation, so human messages have an author like
  any other.
- **Duplicate check:** normalised body = trimmed, whitespace runs collapsed, lower-cased. Look up
  `(participant_id, body_hash)` with `created_at > now - 10 min` before inserting.
- **Read cursor:** a read sets `last_read_seq = GREATEST(last_read_seq, highest seq shown)`. Posting
  also advances the poster's cursor past its own message.
- **Expiry:** default 24 h from creation, chosen at creation (1 h / 24 h / 7 d), extendable from the
  watch page; maximum 7 days from *now* at any time. Expired = `expires_at < UTC_TIMESTAMP()`.
- **Purge:** `DELETE FROM conversations WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR`, run
  on every conversation create and on 2% of other requests. No cron needed (shared hosting
  friendly). Probe hits older than 7 days are purged the same way. The one-hour grace lets an agent
  still see "this conversation has expired" rather than "unknown link".
- **Activity** rows are written for every agent request (including errors and unknown sub-paths) and
  capped at the newest 500 per conversation (trimmed on insert, 2% of the time). It is diagnostic:
  it answers "did the Confluence AI even reach us, and what did it send?"
- All times stored and computed in UTC (`UTC_TIMESTAMP()`, PHP `gmdate`). The session time zone is
  set to `+00:00` on connect.
