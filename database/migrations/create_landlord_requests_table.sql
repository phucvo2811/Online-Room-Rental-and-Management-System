-- Migration: create landlord_requests table
CREATE TABLE IF NOT EXISTS landlord_requests (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status      VARCHAR(20) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','approved','rejected')),
    note        TEXT,
    admin_note  TEXT,
    reviewed_by INT REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_landlord_requests_user_id ON landlord_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_landlord_requests_status  ON landlord_requests(status);
