<?php
namespace App\Core;

abstract class BaseModel
{
    protected string $table;
    protected string $primaryKey = 'id';

    public function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey}=?",
            [$id]
        );
    }

    public function create(array $data): int
    {
        $cols  = implode(', ', array_keys($data));
        $holes = implode(', ', array_fill(0, count($data), '?'));
        return Database::insert(
            "INSERT INTO {$this->table} ($cols) VALUES ($holes)",
            array_values($data)
        );
    }

    public function update(int $id, array $data): int
    {
        $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
        return Database::execute(
            "UPDATE {$this->table} SET $sets WHERE {$this->primaryKey}=?",
            [...array_values($data), $id]
        );
    }

    public function delete(int $id): int
    {
        return Database::execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey}=?",
            [$id]
        );
    }
}