<?php
namespace App\Core;

use PDO;
use PDOStatement;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function init(array $cfg): void
    {
        if (self::$pdo !== null) return;
        $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']}";
        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h2>Lỗi kết nối CSDL</h2><p>' . $e->getMessage() . '</p>');
        }
    }

    public static function pdo(): PDO { return self::$pdo; }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int
    {
        $sql .= ' RETURNING id';
        $stmt = self::query($sql, $params);
        $row  = $stmt->fetch();
        return (int)($row['id'] ?? 0);
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function beginTransaction(): void { self::$pdo->beginTransaction(); }
    public static function commit(): void           { self::$pdo->commit(); }
    public static function rollback(): void         { self::$pdo->rollBack(); }
}