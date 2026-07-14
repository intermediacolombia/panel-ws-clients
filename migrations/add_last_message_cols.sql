-- Migración: desnormalizar last_message y last_direction en conversations
-- Ejecutar una sola vez en producción

ALTER TABLE messages
  ADD INDEX IF NOT EXISTS idx_conv_created (conversation_id, created_at);

ALTER TABLE conversations
  ADD COLUMN IF NOT EXISTS last_message   TEXT         NULL AFTER last_message_at,
  ADD COLUMN IF NOT EXISTS last_direction ENUM('in','out') NULL AFTER last_message;

-- Backfill con el último mensaje real de cada conversación
UPDATE conversations c
JOIN (
  SELECT m.conversation_id, m.content, m.direction
  FROM messages m
  INNER JOIN (
    SELECT conversation_id, MAX(created_at) AS max_at
    FROM messages
    GROUP BY conversation_id
  ) lm ON lm.conversation_id = m.conversation_id AND lm.max_at = m.created_at
) latest ON latest.conversation_id = c.id
SET c.last_message   = latest.content,
    c.last_direction = latest.direction;
