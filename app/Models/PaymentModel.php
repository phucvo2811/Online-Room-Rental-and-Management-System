<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class PaymentModel extends BaseModel
{
    protected string $table = 'payments';

    /** Packages available for purchase */
    public const PACKAGES = [
        '7_days'  => ['days' => 7,  'label' => 'PRO 7 ngày',  'amount' => 49000],
        '30_days' => ['days' => 30, 'label' => 'PRO 30 ngày', 'amount' => 149000],
        '90_days' => ['days' => 90, 'label' => 'PRO 90 ngày', 'amount' => 399000],
    ];

    /** Create a new pending payment, return inserted ID */
    public function createPending(int $userId, string $packageType, string $txnRef, string $ip): int
    {
        $amount = self::PACKAGES[$packageType]['amount'] ?? 0;
        return $this->create([
            'user_id'      => $userId,
            'amount'       => $amount,
            'package_type' => $packageType,
            'status'       => 'pending',
            'vnp_txn_ref'  => $txnRef,
            'ip_address'   => $ip,
        ]);
    }

    /** Find payment by our transaction reference */
    public function findByTxnRef(string $txnRef): ?array
    {
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE vnp_txn_ref = ?",
            [$txnRef]
        );
    }

    /** Mark payment as success and store VNPay metadata */
    public function markSuccess(int $id, array $vnpData): int
    {
        return $this->update($id, [
            'status'             => 'success',
            'vnp_transaction_no' => $vnpData['vnp_TransactionNo'] ?? null,
            'vnp_bank_code'      => $vnpData['vnp_BankCode']      ?? null,
            'vnp_response_code'  => $vnpData['vnp_ResponseCode']  ?? null,
            'vnp_pay_date'       => $vnpData['vnp_PayDate']        ?? null,
        ]);
    }

    /** Mark payment as failed */
    public function markFailed(int $id, string $responseCode): int
    {
        return $this->update($id, [
            'status'             => 'failed',
            'vnp_response_code'  => $responseCode,
        ]);
    }

    /** Append an audit log entry (IPN / return / create) */
    public function log(int|null $paymentId, string $type, array $rawData): void
    {
        Database::insert(
            "INSERT INTO payment_logs (payment_id, type, raw_data) VALUES (?, ?, ?)",
            [$paymentId, $type, json_encode($rawData, JSON_UNESCAPED_UNICODE)]
        );
    }

    /** Recent payments for admin */
    public function getAll(int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT p.*, u.name AS user_name, u.email AS user_email
             FROM {$this->table} p
             JOIN users u ON u.id = p.user_id
             ORDER BY p.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /** Payments of a single user */
    public function getForUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }

    /** Revenue stats for admin dashboard */
    public function stats(): array
    {
        $row = Database::fetch(
            "SELECT
                COUNT(*)                              AS total,
                SUM(status = 'success')               AS success_count,
                SUM(IF(status='success', amount, 0))  AS revenue
             FROM {$this->table}"
        );
        return $row ?? ['total' => 0, 'success_count' => 0, 'revenue' => 0];
    }
}
