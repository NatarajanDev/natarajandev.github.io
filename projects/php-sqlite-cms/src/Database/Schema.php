<?php

declare(strict_types=1);

namespace Cms\Database;

/**
 * Portable column/table definitions.
 *
 * Migrations describe tables once and this class emits either SQLite or MySQL
 * syntax, which keeps the same codebase runnable on both engines.
 */
final class Schema
{
    public function __construct(private Connection $db)
    {
    }

    public function driver(): string
    {
        return (string) $this->db->config('driver');
    }

    public function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    public function id(string $name = 'id'): string
    {
        return $this->isSqlite()
            ? sprintf('%s INTEGER PRIMARY KEY AUTOINCREMENT', $name)
            : sprintf('%s INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $name);
    }

    public function integer(string $name, bool $nullable = false, mixed $default = null): string
    {
        return $this->column($name, 'INTEGER', $nullable, $default);
    }

    public function boolean(string $name, bool $default = false): string
    {
        return $this->isSqlite()
            ? sprintf('%s INTEGER NOT NULL DEFAULT %d', $name, $default ? 1 : 0)
            : sprintf('%s TINYINT(1) NOT NULL DEFAULT %d', $name, $default ? 1 : 0);
    }

    public function string(string $name, int $length = 255, bool $nullable = false, mixed $default = null): string
    {
        return $this->column($name, $this->isSqlite() ? 'TEXT' : sprintf('VARCHAR(%d)', $length), $nullable, $default);
    }

    public function text(string $name, bool $nullable = true): string
    {
        return $this->column($name, $this->isSqlite() ? 'TEXT' : 'LONGTEXT', $nullable, null);
    }

    public function datetime(string $name, bool $nullable = true): string
    {
        return $this->column($name, $this->isSqlite() ? 'TEXT' : 'DATETIME', $nullable, null);
    }

    public function date(string $name, bool $nullable = true): string
    {
        return $this->column($name, $this->isSqlite() ? 'TEXT' : 'DATE', $nullable, null);
    }

    /**
     * Create a table.
     *
     * @param list<string> $columns
     * @param array{unique?: array<string, list<string>>, index?: array<string, list<string>>, foreign?: list<string>} $options
     */
    public function create(string $table, array $columns, array $options = []): void
    {
        $definitions = $columns;

        foreach (($options['unique'] ?? []) as $name => $columnsList) {
            $definitions[] = sprintf('CONSTRAINT %s UNIQUE (%s)', $name, implode(', ', $columnsList));
        }

        $sql = sprintf('CREATE TABLE IF NOT EXISTS %s (%s)%s', $table, implode(', ', $definitions), $this->tableSuffix());
        $this->db->exec($sql);

        foreach (($options['index'] ?? []) as $name => $columnsList) {
            $this->index($table, $name, $columnsList);
        }
    }

    /** @param list<string> $columns */
    public function index(string $table, string $name, array $columns, bool $unique = false): void
    {
        $sql = sprintf(
            'CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)',
            $unique ? 'UNIQUE ' : '',
            $name,
            $table,
            implode(', ', $columns)
        );

        if (!$this->isSqlite()) {
            // MySQL has no "IF NOT EXISTS" for indexes.
            $sql = sprintf(
                'CREATE %sINDEX %s ON %s (%s)',
                $unique ? 'UNIQUE ' : '',
                $name,
                $table,
                implode(', ', $columns)
            );
            try {
                $this->db->exec($sql);
            } catch (\Throwable) {
                // Index already exists — safe to ignore on repeated migrations.
            }

            return;
        }

        $this->db->exec($sql);
    }

    public function drop(string $table): void
    {
        $this->db->exec(sprintf('DROP TABLE IF EXISTS %s', $table));
    }

    public function hasTable(string $table): bool
    {
        return $this->db->tableExists($table);
    }

    /** Add a column if it does not exist yet (used by later migrations). */
    public function addColumn(string $table, string $definition): void
    {
        try {
            $this->db->exec(sprintf('ALTER TABLE %s ADD COLUMN %s', $table, $definition));
        } catch (\Throwable) {
            // Column exists already.
        }
    }

    private function column(string $name, string $type, bool $nullable, mixed $default): string
    {
        $sql = sprintf('%s %s', $name, $type);
        $sql .= $nullable ? ' NULL' : ' NOT NULL';
        if ($default !== null) {
            $sql .= is_string($default) ? " DEFAULT '" . $default . "'" : ' DEFAULT ' . $default;
        }

        return $sql;
    }

    private function tableSuffix(): string
    {
        return $this->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
}
