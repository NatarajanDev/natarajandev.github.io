<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * User administration (administrators only — enforced by route middleware).
 */
final class UserController extends Controller
{
    private const ROLES = ['admin', 'editor', 'author'];
    private const STATUSES = ['active', 'suspended'];

    public function index(): Response
    {
        $this->app->view()->setMeta(['title' => 'Users', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/users/index', [
            'users' => $this->users()->all(),
            'counts' => $this->users()->counts(),
            'attempts' => $this->users()->recentAttempts(12),
            'roles' => self::ROLES,
        ]);
    }

    public function create(): Response
    {
        $this->app->view()->setMeta(['title' => 'New user', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/users/form', [
            'user' => null,
            'roles' => self::ROLES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(): Response
    {
        $request = Request::capture();
        $minLength = (int) $this->app->config('security.password_min_length', 8);

        $validator = $this->validate($request->all(), [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'password' => 'required|min:' . $minLength,
            'password_confirmation' => 'same:password',
            'role' => 'in:admin,editor,author',
            'status' => 'in:active,suspended',
        ], [
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Password confirmation',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/users/create', $request->all());
        }

        if ($this->users()->emailExists($request->string('email'))) {
            $this->app->flash()->error('That email address is already registered.');
            return $this->redirect('/admin/users/create');
        }

        $id = $this->users()->create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'role' => $request->string('role', 'author'),
            'status' => $request->string('status', 'active'),
            'bio' => $request->string('bio'),
            'website' => $request->string('website'),
            'twitter' => $request->string('twitter'),
        ]);

        audit()->record('user.created', 'user', $id, ['email' => $request->string('email'), 'role' => $request->string('role')]);
        $this->app->flash()->success('User created.');

        return $this->redirect('/admin/users');
    }

    public function edit(string $id): Response
    {
        $user = $this->users()->find((int) $id);
        if ($user === null) {
            return $this->redirect('/admin/users');
        }

        $this->app->view()->setMeta(['title' => 'Edit user: ' . $user['name'], 'robots' => 'noindex,nofollow']);

        return $this->view('admin/users/form', [
            'user' => $user,
            'roles' => self::ROLES,
            'statuses' => self::STATUSES,
            'postCount' => (int) $this->app->db()->scalar('SELECT COUNT(*) FROM posts WHERE user_id = ?', [(int) $user['id']]),
        ]);
    }

    public function update(string $id): Response
    {
        $user = $this->users()->find((int) $id);
        if ($user === null) {
            return $this->redirect('/admin/users');
        }

        $request = Request::capture();
        $validator = $this->validate($request->all(), [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'role' => 'in:admin,editor,author',
            'status' => 'in:active,suspended',
        ], ['name' => 'Name', 'email' => 'Email']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/users/' . $id . '/edit', $request->all());
        }

        if ($this->users()->emailExists($request->string('email'), (int) $user['id'])) {
            $this->app->flash()->error('Another account already uses that email address.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        $role = $request->string('role', 'author');
        $status = $request->string('status', 'active');

        // Never let the last administrator lock everyone out.
        if ((int) $user['id'] === (int) $this->app->auth()->id() && ($role !== 'admin' || $status !== 'active')) {
            $this->app->flash()->error('You cannot remove your own administrator access.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        if ($user['role'] === 'admin' && ($role !== 'admin' || $status !== 'active') && $this->users()->countAdmins() <= 1) {
            $this->app->flash()->error('At least one active administrator must remain.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        $this->users()->update((int) $user['id'], [
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'role' => $role,
            'status' => $status,
            'bio' => $request->string('bio'),
            'website' => $request->string('website'),
            'twitter' => $request->string('twitter'),
        ]);

        $password = (string) $request->input('password', '');
        if ($password !== '') {
            $minLength = (int) $this->app->config('security.password_min_length', 8);
            if (mb_strlen($password) < $minLength) {
                $this->app->flash()->error(sprintf('The password must be at least %d characters.', $minLength));
                return $this->redirect('/admin/users/' . $id . '/edit');
            }
            $this->users()->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
            $this->users()->deleteRememberTokens((int) $user['id']);
        }

        audit()->record('user.updated', 'user', (int) $user['id'], ['email' => $request->string('email'), 'role' => $role]);
        $this->app->flash()->success('User updated.');

        return $this->redirect('/admin/users');
    }

    public function destroy(string $id): Response
    {
        $user = $this->users()->find((int) $id);
        if ($user === null) {
            return $this->redirect('/admin/users');
        }

        if ((int) $user['id'] === (int) $this->app->auth()->id()) {
            $this->app->flash()->error('You cannot delete your own account.');
            return $this->redirect('/admin/users');
        }

        if ($user['role'] === 'admin' && $this->users()->countAdmins() <= 1) {
            $this->app->flash()->error('At least one active administrator must remain.');
            return $this->redirect('/admin/users');
        }

        // Keep the content, detach the author.
        $fallback = (int) $this->app->auth()->id();
        $this->app->db()->run('UPDATE posts SET user_id = ? WHERE user_id = ?', [$fallback, (int) $user['id']]);
        $this->app->db()->run('UPDATE pages SET user_id = ? WHERE user_id = ?', [$fallback, (int) $user['id']]);

        $this->users()->delete((int) $user['id']);
        audit()->record('user.deleted', 'user', (int) $user['id'], ['email' => $user['email']]);
        $this->app->flash()->success('User deleted. Their posts were reassigned to you.');

        return $this->redirect('/admin/users');
    }

    public function clearAttempts(): Response
    {
        $affected = $this->users()->pruneAttempts(0);
        audit()->record('security.attempts_cleared', 'security', null, ['affected' => $affected]);
        $this->app->flash()->success(sprintf('Cleared %d login attempt record%s.', $affected, $affected === 1 ? '' : 's'));

        return $this->redirect('/admin/users');
    }
}
