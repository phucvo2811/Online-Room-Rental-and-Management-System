-- Migration: Add view_count column to posts table
-- Created: 2025

ALTER TABLE posts ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0;
