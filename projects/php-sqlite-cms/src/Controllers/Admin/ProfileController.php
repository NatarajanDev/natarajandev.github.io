<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * The signed-in user's own profile and password.
 */
final class ProfileController extends Controller
{
    public function index(): Response
    {
        $user = $this->app->auth()->user();

        $this->app->view()->setMeta(['title' => 'My profile', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/profile', [
            'user' => $user,
            'stats' => [
                'posts' => (int) $this->app->db()->scalar('SELECT COUNT(*) FROM posts WHERE user_id = ?', [(int) $user['id']]),
                'published' => (int) $this->app->db()->scalar("SELECT COUNT(*) FROM posts WHERE user_id = ? AND status = 'published'", [(int) $user['id']]),
                'views' => (int) $this->app->db()->scalar('SELECT COALESCE(SUM(views), 0) FROM posts WHERE user_id = ?', [(int) $user['id']]),
                'comments' => (int) $this->app->db()->scalar(
                    'SELECT COUNT(*) FROM comments c JOIN posts p ON p.id = c.post_id WHERE p.user_id = ?',
                    [(int) $user['id']]
                ),
            ],
            'sessions' => $this->users()->recentAttempts(6),
        ]);
    }

    public function update(): Response
    {
        $request = Request::capture();
        $user = $this->app->auth()->user();

        $validator = $this->validate($request->all(), [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'website' => 'url',
            'twitter' => 'max:60',
        ], ['name' => 'Name', 'email' => 'Email']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/profile', $request->all());
        }

        if ($this->users()->emailExists($request->string('email'), (int) $user['id'])) {
            $this->app->flash()->error('That email address belongs to another account.');
            return $this->redirect('/admin/profile');
        }

        $this->users()->update((int) $user['id'], [
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'bio' => $request->string('bio'),
            'website' => $request->string('website'),
            'twitter' => $request->string('twitter'),
        ]);

        audit()->record('profile.updated', 'user', (int) $user['id']);
        $this->app->flash()->success('Profile saved.');

        return $this->redirect('/admin/profile');
    }

    public function password(): Response
    {
        $request = Request::capture();
        $user = $this->users()->findWithHash((int) $this->app->auth()->id());
        $minLength = (int) $this->app->config('security.password_min_length', 8);

        $validator = $this->validate($request->all(), [
            'current_password' => 'required',
            'password' => 'required|min:' . $minLength,
            'password_confirmation' => 'same:password',
        ], [
            'current_password' => 'Current password',
            'password' => 'New password',
            'password_confirmation' => 'Password confirmation',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/admin/profile', $request->all());
        }

        if ($user === null || !password_verify((string) $request->input('current_password'), (string) $user['password_hash'])) {
            $this->app->flash()->error('Your current password is not correct.');
            return $this->redirect('/admin/profile');
        }

        $this->users()->updatePassword((int) $user['id'], password_hash((string) $request->input('password'), PASSWORD_DEFAULT));
        $this->users()->deleteRememberTokens((int) $user['id']);

        audit()->record('profile.password_changed', 'user', (int) $user['id']);
        $this->app->flash()->success('Password changed. Other devices will need to sign in again.');

        return $this->redirect('/admin/profile');
    }
}
