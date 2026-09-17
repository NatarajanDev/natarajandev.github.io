<?php

declare(strict_types=1);

namespace Cms\Controllers;

use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Browser installer. Runs migrations, creates the first administrator and
 * optionally seeds demo content. Disabled as soon as an account exists.
 */
final class InstallController extends Controller
{
    public function show(): Response
    {
        if ($this->app->installer()->isInstalled()) {
            $this->app->flash()->info('This site is already installed.');
            return $this->redirect('/admin/login');
        }

        return $this->view('auth/install', [
            'meta' => ['title' => 'Install', 'robots' => 'noindex,nofollow'],
            'requirements' => $this->requirements(),
            'values' => [
                'site_title' => (string) $this->settings()->get('site_title', 'Nova CMS'),
                'name' => '',
                'email' => '',
            ],
        ]);
    }

    public function install(): Response
    {
        if ($this->app->installer()->isInstalled()) {
            $this->app->flash()->info('This site is already installed.');
            return $this->redirect('/admin/login');
        }

        $request = Request::capture();
        $minLength = (int) $this->app->config('security.password_min_length', 8);

        $validator = $this->validate($request->all(), [
            'site_title' => 'required|min:2|max:120',
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'password' => 'required|min:' . $minLength,
            'password_confirmation' => 'same:password',
        ], [
            'site_title' => 'Site title',
            'name' => 'Your name',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Password confirmation',
        ]);

        if ($validator->fails()) {
            $this->app->flash()->error(implode(' ', $validator->flatErrors()));

            return $this->redirect('/install');
        }

        $executed = $this->app->installer()->migrate();
        $adminId = $this->app->installer()->createAdmin(
            $request->string('name'),
            $request->string('email'),
            (string) $request->input('password')
        );

        $this->settings()->setMany([
            'site_title' => $request->string('site_title'),
            'site_tagline' => $request->string('tagline', 'A fresh publication built on Nova CMS'),
        ]);
        $this->settings()->refresh();

        if ($request->bool('demo_content', true)) {
            $this->app->installer()->seedDemo($adminId);
        }

        audit()->record('install.completed', 'system', null, ['migrations' => $executed]);
        $this->app->flash()->success('Installation complete — sign in with the account you just created.');

        return $this->redirect('/admin/login');
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    private function requirements(): array
    {
        $storage = (string) $this->app->path('storage');
        $uploads = (string) $this->app->path('uploads');

        return [
            [
                'label' => 'PHP 8.1 or newer',
                'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
                'detail' => 'Running ' . PHP_VERSION,
            ],
            [
                'label' => 'PDO SQLite extension',
                'ok' => extension_loaded('pdo_sqlite'),
                'detail' => extension_loaded('pdo_sqlite') ? 'Available' : 'Missing — enable pdo_sqlite in php.ini',
            ],
            [
                'label' => 'MBString extension',
                'ok' => extension_loaded('mbstring'),
                'detail' => extension_loaded('mbstring') ? 'Available' : 'Missing — recommended for UTF-8 handling',
            ],
            [
                'label' => 'Storage directory writable',
                'ok' => is_writable($storage),
                'detail' => $storage,
            ],
            [
                'label' => 'Uploads directory writable',
                'ok' => is_writable($uploads),
                'detail' => $uploads,
            ],
            [
                'label' => 'GD extension (thumbnails)',
                'ok' => extension_loaded('gd'),
                'detail' => extension_loaded('gd') ? 'Available' : 'Optional — uploads work without thumbnails',
            ],
        ];
    }
}
