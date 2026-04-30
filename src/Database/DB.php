<?php

namespace Vatts\Database;

use PDO;
use PDOException;
use Exception;

class DB
{
    protected static ?PDO $pdo = null;

    /**
     * Inicializa a conexão PDO com o banco
     */
    public static function init(array $config): void
    {
        $driver = $config['driver'] ?? 'mysql';

        try {
            if ($driver === 'sqlite') {
                $database = $config['database'] ?? ':memory:';
                $dsn = "sqlite:{$database}";
                self::$pdo = new PDO($dsn);
            } else {
                $host = $config['host'] ?? '127.0.0.1';
                $database = $config['database'] ?? '';
                $username = $config['username'] ?? 'root';
                $password = $config['password'] ?? '';
                $charset = $config['charset'] ?? 'utf8mb4';

                $dsn = "{$driver}:host={$host};dbname={$database};charset={$charset}";
                self::$pdo = new PDO($dsn, $username, $password);
            }

            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Retorna a instância ativa do PDO
     */
    public static function getPdo(): PDO
    {
        if (!self::$pdo) {
            throw new Exception("Database is not initialized. Check your init() config.");
        }
        return self::$pdo;
    }
}