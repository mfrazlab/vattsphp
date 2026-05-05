<?php

namespace Vatts\Database;

use PDO;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;
use JsonSerializable;
use Exception;

/**
 * @method static static[] where(string|array $column, mixed $operator = null, mixed $value = null)
 * @method static static orderBy(string $column, string $direction = 'ASC')
 * @method static static limit(int $limit)
 * @method static static|null get(mixed ...$args)
 * @method static static|null find(mixed ...$args)
 * @method static static|null first()
 * @method static static[] all()
 */
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

    /** @var array Atributos reais (dados do banco) populados nesta instância */
    protected array $attributes = [];

    // =========================================================================
    // CACHES DE ALTA PERFORMANCE (Evita Reflection repetitivo e I/O de Banco)
    // =========================================================================
    protected static ?PDO $pdoInstance = null;
    protected static array $cachedTableNames = [];
    protected static array $cachedDefaults = [];
    protected static array $classProperties = [];

    // Variáveis internas para montar as Queries (Method Chaining)
    protected array $qbWheres = [];
    protected array $qbParams = [];
    protected string $qbOrderBy = '';
    protected string $qbLimit = '';
    protected int $qbParamCounter = 0;

    /**
     * @param array $attributes Atributos iniciais
     * @param bool $syncSchema Define se deve sincronizar o schema (desativado na hidratação por performance)
     */
    public function __construct(array $attributes = [], bool $syncSchema = true)
    {
        if ($syncSchema) {
            static::syncSchema();
        }

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    // =========================================================================
    // GERENCIAMENTO DA CONEXÃO PDO (SINGLETON CACHE)
    // =========================================================================

    /**
     * Retorna a conexão PDO, abrindo apenas se ainda não existir nesta requisição.
     */
    protected static function getPdoConnection(): PDO
    {
        if (self::$pdoInstance === null) {
            self::$pdoInstance = DB::getPdo(); // Chama a classe externa apenas 1x
        }
        return self::$pdoInstance;
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
        // Verifica as propriedades mapeadas em cache para evitar ReflectionProperty
        $props = static::getClassProperties();

        if (in_array($key, $props, true)) {
            $this->$key = $value;
        } else {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Retorna lista em cache de propriedades públicas (não-estáticas)
     */
    protected static function getClassProperties(): array
    {
        $class = static::class;
        if (!isset(self::$classProperties[$class])) {
            $ref = new ReflectionClass($class);
            $props = [];
            foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                if (!$prop->isStatic()) {
                    $props[] = $prop->getName();
                }
            }
            self::$classProperties[$class] = $props;
        }
        return self::$classProperties[$class];
    }

    /**
     * Retorna defaults em cache para evitar ReflectionClass massivo
     */
    protected static function getClassDefaults(): array
    {
        $class = static::class;
        if (!isset(self::$cachedDefaults[$class])) {
            $ref = new ReflectionClass($class);
            self::$cachedDefaults[$class] = $ref->getDefaultProperties();
        }
        return self::$cachedDefaults[$class];
    }

    protected function getRecordData(): array
    {
        $data = $this->attributes;

        // Extrai as variáveis do objeto de forma segura (ignora uninitialized types e mantem os NULLs)
        $objectVars = get_object_vars($this);

        // Puxa as propriedades via cache, milissegundos mais rápido que Reflection
        foreach (static::getClassProperties() as $propName) {
            // Usa array_key_exists em vez de isset() porque isset() retorna false para propriedades com valor NULL.
            if (array_key_exists($propName, $objectVars)) {
                $data[$propName] = $objectVars[$propName];
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
        $defaults = static::getClassDefaults();
        $hidden = $defaults['hidden'] ?? [];

        foreach ($hidden as $hiddenField) {
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
        $pdo = static::getPdoConnection(); // Usa a conexão do cache
        $data = $this->getRecordData();

        $isInsert = empty($data['id']);

        $defaults = static::getClassDefaults();
        $schemaDef = $defaults['schema'] ?? [];

        $useTimestamps = $defaults['usesTimestamps'] ?? true;
        if (isset($schemaDef['timestamps']) && $schemaDef['timestamps'] === 'timestamps') {
            $useTimestamps = true;
        }

        // --- FILTRO DE COLUNAS PERMITIDAS ---
        $allowedColumns = array_keys($schemaDef);
        $allowedColumns[] = 'id';
        $data = array_intersect_key($data, array_flip($allowedColumns));

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
            $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);
            $placeholders = array_map(fn($col) => ":{$col}", $columns);

            $sql = "INSERT INTO `{$table}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
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
                $sets[] = "`{$col}` = :{$col}";
            }

            $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE `id` = :id";
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
        $stmt = static::getPdoConnection()->prepare("DELETE FROM `{$table}` WHERE `id` = :id");
        return $stmt->execute(['id' => $data['id']]);
    }

    // =========================================================================
    // QUERY BUILDER (ENCADEAMENTO E MÉTODOS ESTÁTICOS MÁGICOS)
    // =========================================================================

    public static function __callStatic($name, $arguments)
    {
        $instance = new static([], false); // Bypassa o sync aqui, faremos no get()
        $method = '_' . $name;

        if (method_exists($instance, $method)) {
            return $instance->$method(...$arguments);
        }

        throw new Exception("Static method {$name} does not exist on " . static::class);
    }

    public function __call($name, $arguments)
    {
        $method = '_' . $name;

        if (method_exists($this, $method)) {
            return $this->$method(...$arguments);
        }

        throw new Exception("Method {$name} does not exist on " . static::class);
    }

    protected function addWhereCondition($column, $operator = null, $value = null): void
    {
        if (is_array($column)) {
            foreach ($column as $k => $v) {
                $this->qbParamCounter++;
                $this->qbWheres[] = "`{$k}` = :qb_{$this->qbParamCounter}";
                $this->qbParams["qb_{$this->qbParamCounter}"] = $v;
            }
        } else {
            if (func_num_args() === 2) {
                $value = $operator;
                $operator = '=';
            }

            $this->qbParamCounter++;
            $this->qbWheres[] = "`{$column}` {$operator} :qb_{$this->qbParamCounter}";
            $this->qbParams["qb_{$this->qbParamCounter}"] = $value;
        }
    }

    protected function _where(...$args): array
    {
        $this->addWhereCondition(...$args);
        return $this->_get();
    }

    protected function _orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->qbOrderBy = " ORDER BY `{$column}` {$direction}";
        return $this;
    }

    protected function _limit(int $limit): static
    {
        $this->qbLimit = " LIMIT {$limit}";
        return $this;
    }

    protected function _first(): ?static
    {
        $this->_limit(1);
        $results = $this->_get();
        return $results[0] ?? null;
    }

    protected function _find(...$args): ?static
    {
        if (count($args) === 1) {
            if (is_array($args[0])) {
                $this->addWhereCondition($args[0]);
            } else {
                $this->addWhereCondition('id', $args[0]);
            }
        } elseif (count($args) === 2) {
            $this->addWhereCondition($args[0], $args[1]);
        }
        return $this->_first();
    }

    protected function _get(...$args)
    {
        if (!empty($args)) {
            return $this->_find(...$args);
        }

        static::syncSchema();
        $table = static::getTableName();
        $pdo = static::getPdoConnection();

        $sql = "SELECT * FROM `{$table}`";
        if (!empty($this->qbWheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->qbWheres);
        }
        $sql .= $this->qbOrderBy . $this->qbLimit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($this->qbParams);

        return self::hydrate($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    protected function _all(): array
    {
        return $this->_get();
    }

    protected static function hydrate(array $rows): array
    {
        $results = [];
        $props = static::getClassProperties();

        foreach ($rows as $row) {
            // false = pula o syncSchema() na hora de popular dados que já vieram do BD (MUITO mais rápido)
            $instance = new static([], false);

            // Bypass rápido: define os atributos sem chamar o setAttribute individualmente de novo
            foreach ($row as $k => $v) {
                if (in_array($k, $props, true)) {
                    $instance->$k = $v;
                } else {
                    $instance->attributes[$k] = $v;
                }
            }
            $results[] = $instance;
        }
        return $results;
    }

    // =========================================================================
    // SCHEMA SYNC (COM SUPORTE A ENUM E FOREIGN KEYS)
    // =========================================================================

    public static function getTableName(): string
    {
        $class = static::class;

        if (isset(self::$cachedTableNames[$class])) {
            return self::$cachedTableNames[$class];
        }

        $defaults = static::getClassDefaults();

        if (!empty($defaults['table'])) {
            self::$cachedTableNames[$class] = $defaults['table'];
        } else {
            $path = explode('\\', $class);
            self::$cachedTableNames[$class] = strtolower(end($path)) . 's';
        }

        return self::$cachedTableNames[$class];
    }

    protected static function syncSchema(): void
    {
        $table = static::getTableName();

        if (isset(self::$syncedTables[$table])) {
            return;
        }

        $defaults = static::getClassDefaults();
        $schemaDef = $defaults['schema'] ?? [];

        if (empty($schemaDef)) {
            self::$syncedTables[$table] = true;
            return;
        }

        self::$syncedTables[$table] = true;

        $pdo = static::getPdoConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $useTimestamps = $defaults['usesTimestamps'] ?? true;

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

            if (str_starts_with($typeStr, 'enum:')) {
                if ($driver === 'sqlite') {
                    $sqlType = 'TEXT';
                } else {
                    $options = explode(',', substr($type, 5));
                    $optionsStr = implode(', ', array_map(fn($o) => "'" . trim($o) . "'", $options));
                    $sqlType = "ENUM({$optionsStr})";
                }
            }
            elseif (str_starts_with($typeStr, 'foreign:')) {
                $sqlType = 'INT';
                $refData = substr($type, 8);
                $parts = explode('.', $refData);
                if (count($parts) === 2) {
                    $foreignKeys[$col] = [
                        'table' => $parts[0],
                        'column' => $parts[1]
                    ];
                }
            }
            else {
                $sqlType = self::mapType($typeStr, $driver);
            }

            $parsedColumns[$col] = $sqlType;
        }

        $tableExists = false;
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $tableExists = (bool) $stmt->fetch();
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $tableExists = (bool) $stmt->fetch();
        }

        if (!$tableExists) {
            $colsSql = [];
            $colsSql[] = ($driver === 'sqlite') ? "`id` INTEGER PRIMARY KEY AUTOINCREMENT" : "`id` INT AUTO_INCREMENT PRIMARY KEY";

            foreach ($parsedColumns as $col => $sqlType) {
                $colsSql[] = "`{$col}` {$sqlType} NULL";
            }

            if ($useTimestamps) {
                $colsSql[] = "`created_at` DATETIME NULL";
                $colsSql[] = "`updated_at` DATETIME NULL";
            }

            foreach ($foreignKeys as $col => $refData) {
                $colsSql[] = "FOREIGN KEY (`{$col}`) REFERENCES `{$refData['table']}`(`{$refData['column']}`) ON DELETE SET NULL";
            }

            $pdo->exec("CREATE TABLE `{$table}` (" . implode(', ', $colsSql) . ")");
            return;
        }

        $existingCols = [];
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(`{$table}`)");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $existingCols[] = $row['name'];
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $existingCols[] = $row['Field'];
        }

        $expectedCols = array_keys($parsedColumns);
        $expectedCols[] = 'id';
        if ($useTimestamps) {
            $expectedCols[] = 'created_at';
            $expectedCols[] = 'updated_at';
        }

        $toAdd = array_diff($expectedCols, $existingCols);
        $toDrop = array_diff($existingCols, $expectedCols);

        foreach ($toAdd as $col) {
            if (isset($parsedColumns[$col])) {
                $type = $parsedColumns[$col];
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$type} NULL");

                if (isset($foreignKeys[$col]) && $driver !== 'sqlite') {
                    $refData = $foreignKeys[$col];
                    try {
                        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `fk_{$table}_{$col}` FOREIGN KEY (`{$col}`) REFERENCES `{$refData['table']}`(`{$refData['column']}`) ON DELETE SET NULL");
                    } catch (\Exception $e) {}
                }

            } elseif (in_array($col, ['created_at', 'updated_at'])) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` DATETIME NULL");
            }
        }

        foreach ($toDrop as $col) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
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