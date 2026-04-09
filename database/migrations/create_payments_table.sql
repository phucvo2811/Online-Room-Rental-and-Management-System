-- ============================================================
-- Migration: VNPay Payment System
-- Run once against your MySQL database
-- ============================================================

-- Table: payments
-- Stores every payment attempt (pending → success / failed)
CREATE TABLE IF NOT EXISTS payments (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          INT             NOT NULL,
    amount           BIGINT UNSIGNED NOT NULL COMMENT 'Amount in VND (integer)',
    package_type     ENUM('7_days','30_days','90_days') NOT NULL,
    status           ENUM('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
    -- VNPay fields
    vnp_txn_ref      VARCHAR(40)     NOT NULL UNIQUE COMMENT 'Our order code sent to VNPay',
    vnp_transaction_no VARCHAR(40)   NULL     COMMENT 'VNPay transaction number on success',
    vnp_bank_code    VARCHAR(20)     NULL,
    vnp_response_code VARCHAR(5)     NULL     COMMENT 'VNPay response code (00 = success)',
    vnp_pay_date     VARCHAR(20)     NULL     COMMENT 'VNPay payment date string',
    -- Meta
    ip_address       VARCHAR(45)     NULL,
    note             TEXT            NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payments_user    (user_id),
    INDEX idx_payments_status  (status),
    INDEX idx_payments_txn_ref (vnp_txn_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payment_logs
-- Immutable audit trail — every IPN / return callback is logged
CREATE TABLE IF NOT EXISTS payment_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_id  INT UNSIGNED NULL,
    type        ENUM('ipn','return','create') NOT NULL,
    raw_data    JSON         NOT NULL COMMENT 'Full GET/POST params from VNPay',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plog_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
