<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class FinancialRecordModel extends BaseModel
{
    protected string $table = 'financial_records';

    /* ── Real-time summary from live room data ───────────────────────── */

    /**
     * Calculate a landlord's live financial summary from actual room data.
     * Revenue = sum of prices of rooms with occupancy_status = 'rented'.
     * Costs   = saved breakdown from landlord_profiles row.
     */
    public function getLiveSummary(int $userId): array
    {
        // Room statistics
        $stats = Database::fetch(
            "SELECT
                COUNT(*)                                            AS total_rooms,
                SUM(CASE WHEN occupancy_status='rented' THEN 1 ELSE 0 END)  AS occupied_rooms,
                COALESCE(SUM(CASE WHEN occupancy_status='rented' THEN price ELSE 0 END), 0) AS monthly_revenue
             FROM rooms WHERE user_id = ?",
            [$userId]
        );

        $total    = (int)($stats['total_rooms']    ?? 0);
        $occupied = (int)($stats['occupied_rooms'] ?? 0);
        $vacant   = $total - $occupied;
        $revenue  = (float)($stats['monthly_revenue'] ?? 0);
        $rate     = $total > 0 ? round($occupied / $total * 100, 1) : 0.0;

        // Cost breakdown from landlord profile
        $profile  = Database::fetch(
            "SELECT cost_electricity, cost_water, cost_maintenance, cost_other
             FROM landlord_profiles WHERE user_id = ?",
            [$userId]
        ) ?? [];

        $costElectricity = (float)($profile['cost_electricity']  ?? 0);
        $costWater       = (float)($profile['cost_water']        ?? 0);
        $costMaintenance = (float)($profile['cost_maintenance']  ?? 0);
        $costOther       = (float)($profile['cost_other']        ?? 0);
        $totalCost       = $costElectricity + $costWater + $costMaintenance + $costOther;
        $profit          = $revenue - $totalCost;

        // Smart suggestions
        $suggestions = [];
        if ($total > 0 && $rate < 70) {
            $suggestions[] = 'Tỷ lệ lấp đầy dưới 70% — bạn đang có nhiều phòng trống. Hãy cân nhắc điều chỉnh giá hoặc tăng cường quảng bá.';
        }
        if ($revenue > 0 && $totalCost > 0 && $profit < $revenue * 0.2) {
            $suggestions[] = 'Biên lợi nhuận thấp (dưới 20%). Xem lại chi phí hoặc điều chỉnh giá thuê để tối ưu hơn.';
        }
        if ($total > 0 && $rate < 50) {
            $suggestions[] = 'Hơn một nửa phòng đang trống. Hãy thử đăng tin PRO để tăng lượt tiếp cận.';
        }

        return [
            'total_rooms'      => $total,
            'occupied_rooms'   => $occupied,
            'vacant_rooms'     => $vacant,
            'occupancy_rate'   => $rate,
            'monthly_revenue'  => $revenue,
            'cost_electricity' => $costElectricity,
            'cost_water'       => $costWater,
            'cost_maintenance' => $costMaintenance,
            'cost_other'       => $costOther,
            'total_cost'       => $totalCost,
            'monthly_profit'   => $profit,
            'suggestions'      => $suggestions,
        ];
    }

    /**
     * Return the room list with occupancy status for the landlord panel.
     */
    public function getRoomList(int $userId): array
    {
        return Database::fetchAll(
            "SELECT r.id, r.title, r.price, r.occupancy_status,
                    rb.name AS property_name
             FROM rooms r
             LEFT JOIN room_blocks rb ON r.property_id = rb.id
             WHERE r.user_id = ?
             ORDER BY r.occupancy_status DESC, r.price DESC",
            [$userId]
        );
    }

    /**
     * Monthly revenue trend — last N months from financial_records snapshots.
     */
    public function getMonthlyTrend(int $userId, int $months = 6): array
    {
        return Database::fetchAll(
            "SELECT
                TO_CHAR(record_date, 'MM/YYYY')        AS month_label,
                ROUND(AVG(monthly_revenue)::numeric, 0) AS revenue,
                ROUND(AVG(monthly_profit)::numeric,  0) AS profit
             FROM {$this->table}
             WHERE user_id = ?
               AND record_date >= CURRENT_DATE - INTERVAL '1 month' * ?
             GROUP BY TO_CHAR(record_date, 'MM/YYYY'), DATE_TRUNC('month', record_date)
             ORDER BY DATE_TRUNC('month', record_date) ASC",
            [$userId, $months]
        );
    }

    /**
     * Save a monthly snapshot for trend chart.
     * Called each time the user saves costs.
     */
    public function snapshotForUser(int $userId, array $live): int
    {
        return $this->create([
            'user_id'         => $userId,
            'record_date'     => date('Y-m-d'),
            'rooms_count'     => $live['total_rooms'],
            'monthly_revenue' => $live['monthly_revenue'],
            'monthly_expenses'=> $live['total_cost'],
            'monthly_profit'  => $live['monthly_profit'],
        ]);
    }

    /* ── Legacy methods (kept to avoid breaking anything) ──────────── */

    public function getRecentForUser(int $userId, int $limit = 12): array
    {
        return Database::fetchAll(
            "SELECT * FROM {$this->table} WHERE user_id=? ORDER BY record_date DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function createForUser(int $userId, array $data): int
    {
        $data = array_merge([
            'user_id'         => $userId,
            'record_date'     => date('Y-m-d'),
            'purchase_cost'   => 0,
            'rent_per_room'   => 0,
            'rooms_count'     => 0,
            'monthly_expenses'=> 0,
            'monthly_revenue' => 0,
            'monthly_profit'  => 0,
            'payback_months'  => null,
        ], $data);
        return $this->create($data);
    }
}