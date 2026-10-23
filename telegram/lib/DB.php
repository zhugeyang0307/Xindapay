<?php
require_once __DIR__ . '/../config.php';

class DB {
    private static $pdo = null;

    public static function conn() {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
            } catch (Exception $e) {
                plog('DB连接失败: ' . $e->getMessage());
                return null;
            }
        }
        return self::$pdo;
    }

    public static function query($sql, $args = []) {
        try {
            $stmt = self::conn()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            plog("SQL错误: {$sql} | " . $e->getMessage());
            return [];
        }
    }

    public static function one($sql, $args = []) {
        $r = self::query($sql, $args);
        return $r[0] ?? null;
    }

    public static function exec($sql, $args = []) {
        try {
            $stmt = self::conn()->prepare($sql);
            $stmt->execute($args);
            return $stmt->rowCount();
        } catch (Exception $e) {
            plog("SQL失败: {$sql} | " . $e->getMessage());
            return 0;
        }
    }

    public static function col($sql, $args = []) {
        $r = self::one($sql, $args);
        return $r ? array_values($r)[0] : null;
    }
}
