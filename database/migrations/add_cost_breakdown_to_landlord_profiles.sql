-- Migration: add cost breakdown columns to landlord_profiles
-- These replace the single monthly_expenses field with 4 granular columns.

ALTER TABLE landlord_profiles
    ADD COLUMN IF NOT EXISTS cost_electricity  NUMERIC(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cost_water        NUMERIC(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cost_maintenance  NUMERIC(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cost_other        NUMERIC(12,2) NOT NULL DEFAULT 0;
