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

    /** @var bool Se o model deve usar/criar as colunas created_at e updated_at (pode ser ativado pelo schema também) */
    protected static bool $usesTimestamps = true;

    /** @var array Controla as tabelas já sincronizadas nesta requisição para evitar queries repetidas */
    protected static array $syncedTables = [];

    /** @var array Atributos reais (dados do banco) populados nesta instância */
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

    protected function setAttribute(string $key, $value): void
    {
        if (property_exists($this, $key)) {
            $this->$key = $value;
        } else {
            $this->attributes[$key] = $value;
        }
    }

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
    // SERIALIZAÇÃO (OCULTAR DADOS COMO PASSWORD)
    // =========================================================================

    public function toArray(): array
    {
        $data = $this->getRecordData();
        foreach (static::$hidden as $hiddenField) {
            unset($data[$hiddenField]);
        }
        return $data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // =========================================================================
    // ACTIVE RECORD (SAVE, DELETE)
    // =========================================================================

    public function save(): bool
    {
        static::syncSchema();
        $table = static::getTableName();
        $pdo = DB::getPdo();
        $data = $this->getRecordData();

        $isInsert = empty($data['id']);

        // Verifica se usa timestamp pela classe ou pelo schema
        $useTimestamps = static::$usesTimestamps || (isset(static::$schema['timestamps']) && static::$schema['timestamps'] === 'timestamps');

        if ($useTimestamps) {
            $now = date('Y-m-d H:i:s');
            $data['updated_at'] = $now;
            if ($isInsert) {
                $data['created_at'] = $now;
            }
            $this->setAttribute('updated_at', $data['updated_at']);
            if ($isInsert) $this->setAttribute('created_at', $data['created_at']);
        }

        if ($isInsert) {
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
            $id = $data['id'];
            unset($data['id']);

            $sets = [];
            foreach (array_keys($data) as $col) {
                $sets[] = "{$col} = :{$col}";
            }

            $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
            $data['id'] = $id;

            $stmt = $pdo->prepare($sql);
            return $stmt->execute($data);
        }
    }

    public function delete(): bool
    {
        $data = $this->getRecordData();
        if (empty($data['id'])) return false;

        $table = static::getTableName();
        $stmt = DB::getPdo()->prepare("DELETE FROM {$table} WHERE id = :id");
        return $stmt->execute(['id' => $data['id']]);
    }

    // =========================================================================
    // QUERY BUILDER
    // =========================================================================

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

    public static function orderBy(string $column, string $direction = 'ASC'): array
    {
        static::syncSchema();
        $table = static::getTableName();
        $pdo = DB::getPdo();

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$column} {$direction}");

        return self::hydrate($stmt->fetchAll());
    }

    public static function all(): array
    {
        static::syncSchema();
        $table = static::getTableName();
        $stmt = DB::getPdo()->query("SELECT * FROM {$table}");
        return self::hydrate($stmt->fetchAll());
    }

    protected static function hydrate(array $rows): array
    {
        $results = [];
        foreach ($rows as $row) {
            $results[] = new static($row);
        }
        return $results;
    }

    // =========================================================================
    // SCHEMA SYNC (COM SUPORTE A ENUM E FOREIGN KEYS)
    // =========================================================================

    public static function getTableName(): string
    {
        // Se a classe filha definiu estaticamente
        if (static::$table) {
            return static::$table;
        }
        $path = explode('\\', static::class);
        return strtolower(end($path)) . 's';
    }

    protected static function syncSchema(): void
    {
        $table = static::getTableName();

        if (isset(self::$syncedTables[$table]) || empty(static::$schema)) {
            return;
        }

        self::$syncedTables[$table] = true;

        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // --- Parsing do Schema Avançado ---
        $schemaDef = static::$schema;
        $useTimestamps = static::$usesTimestamps;

        // Remove timestamps e id do schema bruto, trataremos internamente
        if (isset($schemaDef['timestamps'])) {
            $useTimestamps = true;
            unset($schemaDef['timestamps']);
        }
        if (isset($schemaDef['id'])) {
            unset($schemaDef['id']);
        }

        $parsedColumns = [];
        $foreignKeys = [];

        foreach ($schemaDef as $col => $type) {
            $sqlType = 'VARCHAR(255)';
            $typeStr = strtolower(trim($type));

            // Tratamento de ENUM
            if (str_starts_with($typeStr, 'enum:')) {
                if ($driver === 'sqlite') {
                    $sqlType = 'TEXT'; // Fallback para SQLite
                } else {
                    $options = explode(',', substr($type, 5));
                    $optionsStr = implode(', ', array_map(fn($o) => "'" . trim($o) . "'", $options));
                    $sqlType = "ENUM({$optionsStr})";
                }
            }
            // Tratamento de FOREIGN KEY
            elseif (str_starts_with($typeStr, 'foreign:')) {
                // foreign:users.id
                $sqlType = 'INT';
                $ref = substr($type, 8);
                $parts = explode('.', $ref);
                if (count($parts) === 2) {
                    $foreignKeys[$col] = [
                        'table' => $parts[0],
                        'column' => $parts[1]
                    ];
                }
            }
            // Tipos Básicos
            else {
                $sqlType = self::mapType($typeStr, $driver);
            }

            $parsedColumns[$col] = $sqlType;
        }

        // 1. Verifica se a tabela existe
        $tableExists = false;
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $tableExists = (bool) $stmt->fetch();
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $tableExists = (bool) $stmt->fetch();
        }

        // 2. Tabela não existe: CRIA DO ZERO
        if (!$tableExists) {
            $colsSql = [];
            $colsSql[] = ($driver === 'sqlite') ? "id INTEGER PRIMARY KEY AUTOINCREMENT" : "id INT AUTO_INCREMENT PRIMARY KEY";

            foreach ($parsedColumns as $col => $sqlType) {
                $colsSql[] = "{$col} {$sqlType} NULL";
            }

            if ($useTimestamps) {
                $colsSql[] = "created_at DATETIME NULL";
                $colsSql[] = "updated_at DATETIME NULL";
            }

            // Adiciona restrições de Foreign Key na criação
            foreach ($foreignKeys as $col => $ref) {
                $colsSql[] = "FOREIGN KEY ({$col}) REFERENCES {$ref['table']}({$ref['column']}) ON DELETE SET NULL";
            }

            $pdo->exec("CREATE TABLE {$table} (" . implode(', ', $colsSql) . ")");
            return;
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

        $expectedCols = array_keys($parsedColumns);
        $expectedCols[] = 'id';
        if ($useTimestamps) {
            $expectedCols[] = 'created_at';
            $expectedCols[] = 'updated_at';
        }

        $toAdd = array_diff($expectedCols, $existingCols);
        $toDrop = array_diff($existingCols, $expectedCols);

        // Adiciona novas colunas
        foreach ($toAdd as $col) {
            if (isset($parsedColumns[$col])) {
                $type = $parsedColumns[$col];
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$type} NULL");

                // Tenta adicionar a Foreign Key no banco existente (MySQL suporta, SQLite em modo ALTER não)
                if (isset($foreignKeys[$col]) && $driver !== 'sqlite') {
                    $ref = $foreignKeys[$col];
                    try {
                        $pdo->exec("ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_{$col} FOREIGN KEY ({$col}) REFERENCES {$ref['table']}({$ref['column']}) ON DELETE SET NULL");
                    } catch (\Exception $e) {} // Ignora se a chave já existir
                }

            } elseif (in_array($col, ['created_at', 'updated_at'])) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} DATETIME NULL");
            }
        }

        // Deleta colunas removidas
        foreach ($toDrop as $col) {
            try {
                $pdo->exec("ALTER TABLE {$table} DROP COLUMN {$col}");
            } catch (\Exception $e) {}
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
        if ($type === 'date') return 'DATE';
        if ($type === 'datetime') return 'DATETIME';

        return 'VARCHAR(255)';
    }
}