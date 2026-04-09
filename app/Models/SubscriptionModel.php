<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class SubscriptionModel extends BaseModel
{
    protected string $table = 'subscriptions';

    public function getActiveForUser(int $userId): ?array
    {
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE user_id=? AND status='active' AND starts_at <= NOW() AND ends_at >= NOW() ORDER BY ends_at DESC LIMIT 1",
            [$userId]
        );
    }

    public function isPro(int $userId): bool
    {
        return (bool)$this->getActiveForUser($userId);
    }

    public function createForUser(int $userId, string $plan, int $durationDays, float $amount = 0): int
    {
        $starts = date('Y-m-d H:i:s');
        $ends   = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
        return $this->create([
            'user_id'       => $userId,
            'plan'          => $plan,
            'duration_days' => $durationDays,
            'amount'        => $amount,
            'starts_at'     => $starts,
            'ends_at'       => $ends,
            'status'        => 'active',
        ]);
    }

    public function expireForUser(int $userId): int
    {
        return Database::execute(
            "UPDATE {$this->table} SET status='expired' WHERE user_id=? AND status='active'",
            [$userId]
        );
    }
}
