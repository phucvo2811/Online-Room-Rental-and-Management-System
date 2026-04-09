<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class LandlordProfileModel extends BaseModel
{
    protected string $table = 'landlord_profiles';

    public function getByUser(int $userId): ?array
    {
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE user_id = ? LIMIT 1",
            [$userId]
        );
    }

    public function upsert(int $userId, array $data): int
    {
        $profile = $this->getByUser($userId);
        if ($profile) {
            return $this->update((int)$profile['id'], $data);
        }
        $data['user_id'] = $userId;
        return $this->create($data);
    }
}
