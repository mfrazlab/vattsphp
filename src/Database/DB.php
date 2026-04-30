<?php

namespace Vatts\Database;

use PDO;
use PDOException;
use Exception;

class DB
{
    protected static ?PDO $pdo = null;

    /** @var array Guarda a configuração para conectar apenas quando for solicitado */
    protected static array $config = [];

    /**
     * Inicializa o DB Manager APENAS salvando as configurações.
     * Não faz a conexão PDO neste momento (Lazy Loading).
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Retorna a instância ativa do PDO.
     * Conecta ao banco automaticamente na PRIMEIRA vez que for chamado por um Model.
     */
    public static function getPdo(): PDO
    {
        // Se já estiver conectado nesta requisição, apenas devolve a conexão ativa
        if (self::$pdo) {
            return self::$pdo;
        }

        if (empty(self::$config)) {
            throw new Exception("Database is not configured. Check your Vatts::init() config.");
        }

        // Conecta de verdade no MySQL/SQLite agora!
        self::connect();

        return self::$pdo;
    }

    /**
     * Lógica interna que estabelece a conexão real com o banco de dados
     */
    protected static function connect(): void
    {
        $driver = self::$config['driver'] ?? 'mysql';

        try {
            if ($driver === 'sqlite') {
                $database = self::$config['database'] ?? ':memory:';
                $dsn = "sqlite:{$database}";
                self::$pdo = new PDO($dsn);
            } else {
                $host = self::$config['host'] ?? '127.0.0.1';
                $database = self::$config['database'] ?? '';
                $username = self::$config['username'] ?? 'root';
                $password = self::$config['password'] ?? '';
                $charset = self::$config['charset'] ?? 'utf8mb4';

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
}