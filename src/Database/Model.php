<?php

namespace Vatts\Database;

use PDO;
use ReflectionObject;
use ReflectionProperty;
use JsonSerializable;

abstract class Model implements JsonSerializable
{
    /** @var array Define as colunas dinâmicas (ex: ['name' => 'string']) */
    public static array $schema = [];

    /** @var string|null Nome da tabela (se null, pega o plural do nome da classe) */
    protected static ?string $table = null;

    /** @var array Campos que devem ser ocultados ao converter para Array/JSON (ex: ['password']) */
    protected static array $hidden = [];

    /** @var bool Se o model deve usar/criar as colunas created_at e updated_at */
    protected static bool $usesTimestamps = true;

    /** @var array Controla as tabelas já sincronizadas nesta requisição para evitar queries repetidas */
    protected static array $syncedTables = [];

    /** @var array Atributos "mágicos" (quando o dev não declara a propriedade na classe) */
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        static::syncSchema();

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    // =========================================================================
    // MÉTODOS MÁGICOS E TIPAGEM
    // =========================================================================

    public function __get($key) {
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value) {
        $this->setAttribute($key, $value);
    }

    /**
     * Define o valor. Se o usuário criou uma propriedade tipada (public string $name),
     * injeta nela. Se não, joga no array genérico $attributes.
     */
    protected function setAttribute(string $key, $value): void
    {
        if (property_exists($this, $key)) {
            $this->$key = $value;
        } else {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Junta as propriedades tipadas do Model com os atributos mágicos para salvar no banco
     */
    protected function getRecordData(): array
    {
        $data = $this->attributes;
        $reflection = new ReflectionObject($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isInitialized($this)) {
                $data[$prop->getName()] = $prop->getValue($this);
            }
        }
        return $data;
    }

    // =========================================================================
    // ACTIVE RECORD (SAVE, DELETE)
    // =========================================================================

    /**
     * Insere um novo registro ou atualiza se já existir um ID
     */
    public function save(): bool
    {
        static::syncSchema();
        $table = static::getTableName();
        $pdo = DB::getPdo();
        $data = $this->getRecordData();

        $isInsert = empty($data['id']);

        if (static::$usesTimestamps) {
            $now = date('Y-m-d H:i:s');
            $data['updated_at'] = $now;
            if ($isInsert) {
                $data['created_at'] = $now;
            }
            $this->setAttribute('updated_at', $data['updated_at']);
            if ($isInsert) $this->setAttribute('created_at', $data['created_at']);
        }

        if ($isInsert) {
            // INSERT
            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ":{$col}", $columns);

            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($data);

            if ($result) {
                $id = $pdo->lastInsertId();
                $this->setAttribute('id', $id);
            }
            return $result;
        } else {
            // UPDATE
            $id = $data['id'];
            unset($data['id']); // Remove o ID dos dados para não dar update na chave primária

            $sets = [];
            foreach (array_keys($data) as $col) {
                $sets[] = "{$col} = :{$col}";
            }

            $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
            $data['id'] = $id; // Recoloca para o bind do WHERE

            $stmt = $pdo->prepare($sql);
            return $stmt->execute($data);
        }
    }

    /**
     * Deleta o registro atual do banco
     */
    public function delete(): bool
    {
        $data = $this->getRecordData();
        if (empty($data['id'])) return false;

        $table = static::getTableName();
        $stmt = DB::getPdo()->prepare("DELETE FROM {$table} WHERE id = :id");
        return $stmt->execute(['id' => $data['id']]);
    }

    // =========================================================================
    // QUERY BUILDER / ESTÁTICOS
    // =========================================================================

    /**
     * Busca um único registro.
     * Uso: User::get(1) | User::get('email', 'teste@teste.com') | User::get(['status' => 'ativo'])
     */
    public static function get(...$args): ?static
    {
        static::syncSchema();
        $table = static::getTableName();
        $pdo = DB::getPdo();

        $sql = "SELECT * FROM {$table}";
        $params = [];

        if (count($args) === 1) {
            if (is_array($args[0])) {
                $conditions = [];
                foreach ($args[0] as $key => $value) {
                    $conditions[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $conditions);
            } else {
                $sql .= " WHERE id = :id";
                $params['id'] = $args[0];
            }
        } elseif (count($args) === 2) {
            $sql .= " WHERE {$args[0]} = :val";
            $params['val'] = $args[1];
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ? new static($row) : null;
    }

    /**
     * Busca vários registros baseados em condições (Array de Objetos)
     * Uso: User::where('role', 'admin') | User::where(['role' => 'admin', 'active' => 1])
     */
    public static function where($column, $value = null): array
    {
        static::syncSchema();
        $table = static::getTableName();
        $pdo = DB::getPdo();

        $sql = "SELECT * FROM {$table} WHERE ";
        $params = [];

        if (is_array($column)) {
            $conditions = [];
            foreach ($column as $k => $v) {
                $conditions[] = "{$k} = :{$k}";
                $params[$k] = $v;
            }
            $sql .= implode(' AND ', $conditions);
        } else {
            $sql .= "{$column} = :val";
            $params['val'] = $value;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return self::hydrate($stmt->fetchAll());
    }

    /**
     * Retorna todos os registros ordenados
     * Uso: User::orderBy('name', 'DESC')
     */
    public static function orderBy(string $column, string $direction = 'ASC'): array
    {
        static::syncSchema();
        $table = static::getTableName();
        $pdo = DB::getPdo();

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$column} {$direction}");

        return self::hydrate($stmt->fetchAll());
    }

    /**
     * Ponto de partida estático opcional (ex: Model::all())
     */
    public static function all(): array
    {
        static::syncSchema();
        $table = static::getTableName();
        $stmt = DB::getPdo()->query("SELECT * FROM {$table}");

        return self::hydrate($stmt->fetchAll());
    }

    /**
     * Transforma um array de resultados do PDO em um array de Objetos (Models)
     */
    protected static function hydrate(array $rows): array
    {
        $results = [];
        foreach ($rows as $row) {
            $results[] = new static($row);
        }
        return $results;
    }

    // =========================================================================
    // SERIALIZAÇÃO E PROTEÇÃO DE DADOS (JSON / ARRAY)
    // =========================================================================

    /**
     * Converte o model para array, ocultando os campos definidos em $hidden
     */
    public function toArray(): array
    {
        $data = $this->getRecordData();
        foreach (static::$hidden as $hiddenField) {
            unset($data[$hiddenField]);
        }
        return $data;
    }

    /**
     * Executado automaticamente pelo PHP ao fazer json_encode($model)
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // =========================================================================
    // SCHEMA SYNC / CORE
    // =========================================================================

    public static function getTableName(): string
    {
        if (static::$table) {
            return static::$table;
        }
        $path = explode('\\', static::class);
        return strtolower(end($path)) . 's'; // Ex: User -> users
    }

    protected static function syncSchema(): void
    {
        $table = static::getTableName();

        // Se a tabela já foi checada nessa execução, ignora.
        if (isset(self::$syncedTables[$table]) || empty(static::$schema)) {
            return;
        }

        self::$syncedTables[$table] = true;

        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // 1. Verifica se a tabela existe
        $tableExists = false;
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $tableExists = (bool) $stmt->fetch();
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $tableExists = (bool) $stmt->fetch();
        }

        // 2. Tabela não existe: CRIA do zero
        if (!$tableExists) {
            $columns = [];
            $columns[] = ($driver === 'sqlite') ? "id INTEGER PRIMARY KEY AUTOINCREMENT" : "id INT AUTO_INCREMENT PRIMARY KEY";

            foreach (static::$schema as $col => $type) {
                $columns[] = "{$col} " . self::mapType($type, $driver) . " NULL";
            }

            if (static::$usesTimestamps) {
                $columns[] = "created_at DATETIME NULL";
                $columns[] = "updated_at DATETIME NULL";
            }

            $pdo->exec("CREATE TABLE {$table} (" . implode(', ', $columns) . ")");
            return; // Se criou do zero, não precisa sincronizar colunas
        }

        // 3. Tabela existe: Sincroniza Adicionando ou Removendo colunas
        $existingCols = [];
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$table})");
            while ($row = $stmt->fetch()) $existingCols[] = $row['name'];
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
            while ($row = $stmt->fetch()) $existingCols[] = $row['Field'];
        }

        $expectedCols = array_keys(static::$schema);
        $expectedCols[] = 'id';
        if (static::$usesTimestamps) {
            $expectedCols[] = 'created_at';
            $expectedCols[] = 'updated_at';
        }

        $toAdd = array_diff($expectedCols, $existingCols);
        $toDrop = array_diff($existingCols, $expectedCols);

        // Adiciona novas colunas configuradas no $schema
        foreach ($toAdd as $col) {
            if (isset(static::$schema[$col])) {
                $type = self::mapType(static::$schema[$col], $driver);
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$type} NULL");
            } elseif (in_array($col, ['created_at', 'updated_at'])) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} DATETIME NULL");
            }
        }

        // Deleta colunas que foram removidas do $schema
        foreach ($toDrop as $col) {
            try {
                $pdo->exec("ALTER TABLE {$table} DROP COLUMN {$col}");
            } catch (\Exception $e) {} // Ignora falhas em versões antigas do SQLite que não suportam DROP COLUMN
        }
    }

    private static function mapType(string $type, string $driver): string
    {
        $type = strtolower($type);
        if ($type === 'string') return 'VARCHAR(255)';
        if ($type === 'integer' || $type === 'int') return 'INTEGER';
        if ($type === 'boolean' || $type === 'bool') return $driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)';
        if ($type === 'text') return 'TEXT';
        if ($type === 'float' || $type === 'double') return 'DOUBLE';

        return 'VARCHAR(255)'; // Default fallback
    }
}