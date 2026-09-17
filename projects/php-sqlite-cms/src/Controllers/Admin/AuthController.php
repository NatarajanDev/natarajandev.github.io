<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Admin sign-in / sign-out with throttling.
 */
final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if ($this->app->auth()->check()) {
            return $this->redirect('/admin');
        }

        return $this->view('auth/login', [
            'meta' => ['title' => 'Sign in', 'robots' => 'noindex,nofollow'],
            'email' => '',
        ]);
    }

    public function login(): Response
    {
        $request = Request::capture();
        $email = $request->string('email');
        $password = (string) $request->input('password', '');
        $remember = $request->bool('remember');

        if ($email === '' || $password === '') {
            $this->app->flash()->error('Enter your email address and password.');
            return $this->redirect('/admin/login');
        }

        if ($this->app->auth()->attempt($email, $password, $remember)) {
            $user = $this->app->auth()->user();
            audit()->record('auth.login', 'user', $this->app->auth()->id());
            $this->app->flash()->success('Welcome back, ' . ($user['name'] ?? 'editor') . '.');

            $intended = $_SESSION['intended_url'] ?? '/admin';
            unset($_SESSION['intended_url']);

            return $this->redirect(is_string($intended) ? $intended : '/admin');
        }

        $remaining = $this->app->auth()->attemptsRemaining($email);
        $this->app->flash()->error($remaining > 0
            ? sprintf('Those credentials do not match. %d attempt%s left before a temporary lock.', $remaining, $remaining === 1 ? '' : 's')
            : 'Too many failed attempts. Try again in a few minutes.');

        return $this->redirect('/admin/login');
    }

    public function logout(): Response
    {
        audit()->record('auth.logout', 'user', $this->app->auth()->id());
        $this->app->auth()->logout();
        $this->app->flash()->success('You have been signed out.');

        return $this->redirect('/admin/login');
    }
}
