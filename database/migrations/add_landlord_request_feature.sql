-- ============================================================
-- Migration: Landlord Request Feature
-- Date: 2026-04-05
-- Adds landlord_status to users table and creates
-- landlord_requests table for tenant-to-landlord upgrade flow.
-- ============================================================

-- 1. Add landlord_status column to users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS landlord_status VARCHAR(20) DEFAULT 'none'
    CHECK (landlord_status IN ('none', 'pending', 'approved', 'rejected'));

-- 2. Backfill: existing landlords are already approved
UPDATE users
    SET landlord_status = 'approved'
    WHERE role = 'landlord' AND landlord_status = 'none';

-- 3. Create landlord_requests table
CREATE TABLE IF NOT EXISTS landlord_requests (
    id          SERIAL PRIMARY KEY,
    user_id     INT  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    description TEXT,
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'approved', 'rejected')),
    admin_note  TEXT,
    reviewed_by INT REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

-- Only one PENDING request per user at a time
CREATE UNIQUE INDEX IF NOT EXISTS uidx_landlord_requests_user_pending
    ON landlord_requests (user_id)
    WHERE status = 'pending';

-- Index for fast admin queries
CREATE INDEX IF NOT EXISTS idx_landlord_requests_status
    ON landlord_requests (status, created_at DESC);
