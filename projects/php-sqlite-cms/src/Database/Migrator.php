<?php

declare(strict_types=1);

namespace Cms\Database;

/**
 * Runs database/migrations/*.php in filename order and records what ran.
 *
 * Each migration file returns a closure that receives a Schema instance:
 *
 *     return function (Schema $schema): void { ... };
 */
final class Migrator
{
    public function __construct(
        private Connection $db,
        private string $migrationsPath
    ) {
    }

    public function schema(): Schema
    {
        return new Schema($this->db);
    }

    public function ensureRepository(): void
    {
        $this->db->exec(
            $this->schema()->isSqlite()
                ? 'CREATE TABLE IF NOT EXISTS migrations (
                     id INTEGER PRIMARY KEY AUTOINCREMENT,
                     migration TEXT NOT NULL UNIQUE,
                     batch INTEGER NOT NULL DEFAULT 1,
                     ran_at TEXT
                   )'
                : 'CREATE TABLE IF NOT EXISTS migrations (
                     id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                     migration VARCHAR(191) NOT NULL UNIQUE,
                     batch INT NOT NULL DEFAULT 1,
                     ran_at DATETIME NULL
                   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return list<string> */
    public function ran(): array
    {
        $this->ensureRepository();
        $rows = $this->db->all('SELECT migration FROM migrations ORDER BY migration ASC');

        return array_map(static fn (array $row): string => (string) $row['migration'], $rows);
    }

    /** @return list<string> */
    public function pending(): array
    {
        $ran = $this->ran();

        return array_values(array_diff($this->files(), $ran));
    }

    /** @return list<string> */
    public function files(): array
    {
        $files = glob(rtrim($this->migrationsPath, '/') . '/*.php') ?: [];
        $names = array_map(static fn (string $path): string => basename($path, '.php'), $files);
        sort($names);

        return $names;
    }

    /** @return list<string> newly executed migrations */
    public function run(): array
    {
        $this->ensureRepository();
        $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;
        $executed = [];

        foreach ($this->pending() as $migration) {
            $file = rtrim($this->migrationsPath, '/') . '/' . $migration . '.php';
            $callback = require $file;

            if (!is_callable($callback)) {
                throw new \RuntimeException("Migration {$migration} did not return a callable.");
            }

            $callback($this->schema());

            $this->db->exec(
                'INSERT INTO migrations (migration, batch, ran_at) VALUES (?, ?, ?)',
                [$migration, $batch, (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
            );

            $executed[] = $migration;
        }

        return $executed;
    }

    /** @return list<array{migration: string, batch: int, ran_at: ?string}> */
    public function status(): array
    {
        $this->ensureRepository();

        /** @var list<array{migration: string, batch: int, ran_at: ?string}> $rows */
        $rows = $this->db->all('SELECT migration, batch, ran_at FROM migrations ORDER BY migration ASC');

        return $rows;
    }
}
