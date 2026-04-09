<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class RoomTypeModel extends BaseModel
{
    protected string $table = 'room_types';

    public function getAll(): array
    {
        return Database::fetchAll("SELECT * FROM {$this->table} ORDER BY name ASC");
    }

    public function getBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM {$this->table} WHERE slug=?", [$slug]);
    }

    public function getById(int $id): ?array
    {
        return $this->findById($id);
    }

    public function toOptionList(): array
    {
        $types = $this->getAll();
        $list = [];
        foreach ($types as $type) {
            $list[$type['id']] = $type;
        }
        return $list;
    }
}
