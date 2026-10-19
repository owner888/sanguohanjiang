<?php

declare(strict_types=1);

namespace Hanjiang\Database;

use PDO;
use PDOException;
use PDOStatement;

final class DB
{
    private static ?PDO $pdo = null;
    /** @var array<string, mixed> */
    private static array $config = [];

    /**
     * @param array<string, mixed> $config
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        self::connect();
    }

    private static function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'] ?? '127.0.0.1',
            self::$config['port'] ?? 3306,
            self::$config['database'] ?? 'zc_sanguo',
            self::$config['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO(
            $dsn,
            self::$config['username'] ?? 'root',
            self::$config['password'] ?? 'root',
            self::$config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    /**
     * 长进程保活与自动重连 (防 2006 MySQL server has gone away)
     */
    public static function ping(): void
    {
        try {
            self::pdo()->query('SELECT 1');
        } catch (\Throwable) {
            self::connect();
        }
    }

    public static function table(string $table): QueryBuilder
    {
        self::ping();
        $prefix = self::$config['prefix'] ?? '';
        $fullTable = str_starts_with($table, $prefix) ? $table : $prefix . $table;
        return new QueryBuilder(self::pdo(), $fullTable);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        self::ping();
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        self::ping();
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

/**
 * 轻量流式查询构建器 (完全对齐 smart-book / OmniPHP DB 使用习惯)
 */
final class QueryBuilder
{
    /** @var list<string> */
    private array $wheres = [];
    /** @var array<string, mixed> */
    private array $bindings = [];
    private ?int $limit = null;
    private ?string $orderBy = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table
    ) {
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = (string)$operatorOrValue;
        }

        $paramKey = 'p_' . count($this->bindings) . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $this->wheres[] = "`{$column}` {$operator} :{$paramKey}";
        $this->bindings[$paramKey] = $value;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy = "`{$column}` {$dir}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->orderBy !== null) {
            $sql .= ' ORDER BY ' . $this->orderBy;
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $fields = implode('`, `', $columns);
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `{$this->table}` (`{$fields}`) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): int
    {
        $sets = [];
        $params = $this->bindings;
        foreach ($data as $col => $val) {
            $key = 'u_' . $col;
            $sets[] = "`{$col}` = :{$key}";
            $params[$key] = $val;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets);
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM `{$this->table}`";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as c FROM `{$this->table}`";
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}
