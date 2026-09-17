<?php

declare(strict_types=1);

namespace Cms\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper: connections, prepared statements and transactions.
 *
 * Every query that touches user input goes through prepared statements, so the
 * CMS keeps SQL injection out of the picture by construction.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    /** Read a connection setting, e.g. config('driver'). */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = (string) ($this->config['driver'] ?? 'sqlite');

        try {
            $this->pdo = match ($driver) {
                'sqlite' => $this->connectSqlite((string) $this->config['sqlite']),
                'mysql' => $this->connectMysql($this->config['mysql']),
                default => throw new RuntimeException("Unsupported database driver: {$driver}"),
            };
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->pdo;
    }

    private function connectSqlite(string $path): PDO
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create database directory: {$directory}");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // WAL keeps readers unblocked while an editor writes; foreign keys are
        // off by default in SQLite and must be enabled per connection.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        return $pdo;
    }

    /** @param array<string, mixed> $config */
    private function connectMysql(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        return new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Run a prepared statement and return the statement handle.
     *
     * @param array<string|int, mixed> $params
     */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $placeholder = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($placeholder, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $params)->fetchAll();

        return $rows;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Insert a row and return the new id.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );

        $this->run($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Update rows matching a single column.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $table, array $data, string $whereColumn, mixed $whereValue): int
    {
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :where_value',
            $table,
            implode(', ', $assignments),
            $whereColumn
        );

        $params = $data;
        $params['where_value'] = $whereValue;

        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run(sprintf('DELETE FROM %s WHERE %s', $table, $where), $params)->rowCount();
    }

    /** @param array<string|int, mixed> $params */
    public function exec(string $sql, array $params = []): void
    {
        $this->run($sql, $params);
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        $driver = (string) ($this->config['driver'] ?? 'sqlite');
        try {
            if ($driver === 'sqlite') {
                return (bool) $this->scalar(
                    "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
                    [$table]
                );
            }

            return (bool) $this->scalar(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
        } catch (PDOException) {
            return false;
        }
    }
}
