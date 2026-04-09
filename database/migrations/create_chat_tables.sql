-- Chat: Conversations & Messages
-- user1_id is always MIN(id), user2_id is always MAX(id) -- enforced by CHECK

CREATE TABLE IF NOT EXISTS conversations (
    id              SERIAL PRIMARY KEY,
    user1_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user2_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    last_message    TEXT,
    last_message_at TIMESTAMP DEFAULT NOW(),
    user1_typing_at TIMESTAMP,
    user2_typing_at TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT conversations_users_unique UNIQUE (user1_id, user2_id),
    CONSTRAINT conversations_ordered     CHECK  (user1_id < user2_id)
);

CREATE TABLE IF NOT EXISTS messages (
    id              SERIAL PRIMARY KEY,
    conversation_id INT  NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id       INT  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content         TEXT NOT NULL,
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_messages_conv_id  ON messages(conversation_id, id);
CREATE INDEX IF NOT EXISTS idx_conv_user1        ON conversations(user1_id);
CREATE INDEX IF NOT EXISTS idx_conv_user2        ON conversations(user2_id);
