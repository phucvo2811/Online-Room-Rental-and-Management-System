<?php
namespace App\Core;

class Container
{
    private static array $bindings  = [];
    private static array $instances = [];

    public static function set(string $key, mixed $value): void
    {
        self::$instances[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        if (isset(self::$instances[$key])) return self::$instances[$key];
        if (isset(self::$bindings[$key])) {
            self::$instances[$key] = (self::$bindings[$key])();
            return self::$instances[$key];
        }
        throw new \RuntimeException("Container: '{$key}' not found.");
    }

    public static function has(string $key): bool
    {
        return isset(self::$instances[$key]) || isset(self::$bindings[$key]);
    }
}