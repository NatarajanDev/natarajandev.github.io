<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Repository\PostRepository;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Maintenance tools: cache, backups, exports, logs and system health.
 */
final class ToolController extends Controller
{
    public function index(): Response
    {
        $this->app->view()->setMeta(['title' => 'Tools', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/tools/index', [
            'health' => $this->health(),
            'storage' => $this->app->dashboard()->storage(),
            'migrations' => $this->app->installer()->migrator()->status(),
            'pendingMigrations' => $this->app->installer()->migrator()->pending(),
            'logs' => $this->app->log()->dates(),
            'auditCount' => $this->app->activity()->repository()->count(),
        ]);
    }

    public function flushCache(): Response
    {
        $removed = $this->app->cache()->flush();
        audit()->record('tools.cache_flushed', 'system', null, ['files' => $removed]);
        $this->app->flash()->success(sprintf('Cleared %d cached file%s.', $removed, $removed === 1 ? '' : 's'));

        return $this->redirect('/admin/tools');
    }

    public function runMigrations(): Response
    {
        $executed = $this->app->installer()->migrate();
        audit()->record('tools.migrations_run', 'system', null, ['migrations' => $executed]);
        $this->app->flash()->success($executed === []
            ? 'Database is already up to date.'
            : sprintf('Executed %d migration%s.', count($executed), count($executed) === 1 ? '' : 's'));

        return $this->redirect('/admin/tools');
    }

    public function prune(): Response
    {
        $logs = $this->app->log()->prune(14);
        $attempts = $this->users()->pruneAttempts(30);
        $audit = $this->app->activity()->repository()->prune(90);
        $this->users()->pruneRememberTokens();

        audit()->record('tools.pruned', 'system', null, ['logs' => $logs, 'attempts' => $attempts, 'audit' => $audit]);
        $this->app->flash()->success(sprintf(
            'Pruned %d log file(s), %d login attempt(s) and %d audit entr(ies).',
            $logs,
            $attempts,
            $audit
        ));

        return $this->redirect('/admin/tools');
    }

    /** Download a copy of the SQLite database (or a JSON export for MySQL). */
    public function backup(): Response
    {
        $driver = (string) $this->app->config('database.driver', 'sqlite');
        $stamp = date('Y-m-d-His');

        if ($driver === 'sqlite') {
            $path = (string) $this->app->config('database.sqlite');
            if (!is_file($path)) {
                $this->app->flash()->error('Database file not found.');
                return $this->redirect('/admin/tools');
            }

            audit()->record('tools.backup', 'system', null, ['driver' => 'sqlite']);

            return Response::download((string) file_get_contents($path), "cms-backup-{$stamp}.sqlite", 'application/vnd.sqlite3');
        }

        $dump = $this->jsonExport();
        audit()->record('tools.backup', 'system', null, ['driver' => 'mysql']);

        return Response::download($dump, "cms-backup-{$stamp}.json", 'application/json');
    }

    /** Export all content as JSON (portable between installs). */
    public function export(): Response
    {
        audit()->record('tools.export', 'system');

        return Response::download($this->jsonExport(), 'cms-export-' . date('Y-m-d-Hi') . '.json', 'application/json');
    }

    public function logs(): Response
    {
        $request = Request::capture();
        $date = $request->string('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;

        $this->app->view()->setMeta(['title' => 'System log', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/tools/logs', [
            'entries' => $this->app->log()->tail(200, $date),
            'dates' => $this->app->log()->dates(),
            'activeDate' => $date ?? date('Y-m-d'),
        ]);
    }

    public function clearLog(): Response
    {
        $date = Request::capture()->string('date');
        $this->app->log()->clear($date !== '' ? $date : null);
        audit()->record('tools.log_cleared', 'system', null, ['date' => $date]);
        $this->app->flash()->success('Log cleared.');

        return $this->redirect('/admin/tools/logs');
    }

    public function audit(): Response
    {
        $request = Request::capture();
        $page = max(1, $request->int('page', 1));
        $perPage = 30;
        $filters = ['search' => $request->string('q'), 'entity' => $request->string('entity')];

        $result = $this->app->activity()->repository()->paginate($filters, $page, $perPage);
        $paginator = new \Cms\Support\Paginator($result['items'], $result['total'], $perPage, $page, '/admin/audit', $request->query());

        $this->app->view()->setMeta(['title' => 'Audit trail', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/tools/audit', [
            'paginator' => $paginator,
            'entries' => $paginator->items(),
            'filters' => $filters,
        ]);
    }

    /** @return array<string, mixed> */
    private function health(): array
    {
        $uploads = (string) $this->app->path('uploads');
        $storage = (string) $this->app->path('storage');

        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'sqlite_version' => $this->sqliteVersion(),
            'driver' => (string) $this->app->config('database.driver'),
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_upload' => (string) ini_get('upload_max_filesize'),
            'timezone' => date_default_timezone_get(),
            'uploads_writable' => is_writable($uploads),
            'storage_writable' => is_writable($storage),
            'extensions' => array_values(array_filter([
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'mbstring' => extension_loaded('mbstring'),
                'gd' => extension_loaded('gd'),
                'fileinfo' => extension_loaded('fileinfo'),
                'openssl' => extension_loaded('openssl'),
                'json' => extension_loaded('json'),
                'curl' => extension_loaded('curl'),
            ])),
        ];
    }

    private function sqliteVersion(): string
    {
        try {
            return (string) $this->app->db()->scalar('SELECT sqlite_version()');
        } catch (\Throwable) {
            return 'n/a';
        }
    }

    private function jsonExport(): string
    {
        $db = $this->app->db();
        $tables = ['users', 'categories', 'tags', 'posts', 'post_tags', 'pages', 'comments', 'media', 'messages', 'settings'];
        $data = [];

        foreach ($tables as $table) {
            if (!$db->tableExists($table)) {
                continue;
            }
            $rows = $db->all('SELECT * FROM ' . $table);
            if ($table === 'users') {
                // Never export password hashes or tokens.
                $rows = array_map(static function (array $row): array {
                    unset($row['password_hash']);
                    return $row;
                }, $rows);
            }
            $data[$table] = $rows;
        }

        return (string) json_encode([
            'exported_at' => date('c'),
            'app' => (string) $this->app->config('app.name'),
            'tables' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
