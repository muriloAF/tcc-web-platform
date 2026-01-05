-- Ensure chats has last_message and last_message_at
ALTER TABLE chats
  ADD COLUMN IF NOT EXISTS last_message TEXT NULL,
  ADD COLUMN IF NOT EXISTS last_message_at DATETIME NULL;

-- Unread counter per user per chat
CREATE TABLE IF NOT EXISTS chat_unread (
  chat_id INT NOT NULL,
  user_id INT NOT NULL,
  unread_count INT NOT NULL DEFAULT 0,
  PRIMARY KEY (chat_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
