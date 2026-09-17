<?php

declare(strict_types=1);

namespace Cms\Support;

use Cms\App;
use Cms\Repository\UserRepository;

/**
 * Session based authentication with:
 *  - bcrypt/argon password hashing (password_hash)
 *  - login throttling per email+IP
 *  - session id regeneration on privilege change
 *  - optional "remember me" tokens stored as selector + hashed validator
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';
    private const REMEMBER_COOKIE = 'cms_remember';

    private ?array $user = null;

    private bool $resolved = false;

    public function __construct(private App $app)
    {
    }

    /** Currently authenticated user row, or null. */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $userId = $_SESSION[self::SESSION_KEY] ?? null;

        if (is_numeric($userId)) {
            $this->user = (new UserRepository($this->app->db()))->find((int) $userId);
        }

        if ($this->user === null) {
            $this->user = $this->userFromRememberCookie();
            if ($this->user !== null) {
                $_SESSION[self::SESSION_KEY] = (int) $this->user['id'];
            }
        }

        if ($this->user !== null && ($this->user['status'] ?? '') !== 'active') {
            $this->logout();
            $this->user = null;
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function role(): string
    {
        return (string) ($this->user()['role'] ?? 'guest');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /** Admins and editors may review content created by others. */
    public function canModerate(): bool
    {
        return $this->hasRole('admin', 'editor');
    }

    public function attempt(string $email, string $password, bool $remember = false): bool
    {
        $email = strtolower(trim($email));
        $ip = Request::capture()->ip();
        $users = new UserRepository($this->app->db());

        if ($this->tooManyAttempts($email, $ip)) {
            return false;
        }

        $user = $users->findByEmail($email);

        // Always run a hash comparison so response time does not reveal whether
        // the account exists.
        $hash = (string) ($user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv');
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid || ($user['status'] ?? '') !== 'active') {
            $users->recordLoginAttempt($email, $ip, false);

            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $users->recordLoginAttempt($email, $ip, true);
        $this->login($user, $remember);

        return true;
    }

    public function login(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $user['id'];
        $_SESSION['auth_time'] = time();
        $this->user = $user;
        $this->resolved = true;

        (new UserRepository($this->app->db()))->touchLogin((int) $user['id']);

        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        }
    }

    public function logout(): void
    {
        $userId = $this->id();
        if ($userId !== null) {
            (new UserRepository($this->app->db()))->deleteRememberTokens($userId);
        }

        $this->clearRememberCookie();
        unset($_SESSION[self::SESSION_KEY], $_SESSION['auth_time']);
        $this->user = null;
        $this->resolved = true;
        session_regenerate_id(true);
    }

    /** Number of failed attempts left before the account is temporarily locked. */
    public function attemptsRemaining(string $email): int
    {
        $max = (int) $this->app->config('security.login_max_attempts', 5);
        $since = $this->lockoutWindowStart();
        $failed = (int) $this->app->db()->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [strtolower(trim($email)), Request::capture()->ip(), $since]
        );

        return max(0, $max - $failed);
    }

    private function tooManyAttempts(string $email, string $ip): bool
    {
        $max = (int) $this->app->config('security.login_max_attempts', 5);
        $failed = (int) $this->app->db()->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [$email, $ip, $this->lockoutWindowStart()]
        );

        return $failed >= $max;
    }

    private function lockoutWindowStart(): string
    {
        $minutes = (int) $this->app->config('security.login_lockout_minutes', 15);

        return (new \DateTimeImmutable())->modify("-{$minutes} minutes")->format('Y-m-d H:i:s');
    }

    private function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        (new UserRepository($this->app->db()))->storeRememberToken(
            $userId,
            $selector,
            hash('sha256', $validator),
            $expires
        );

        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires' => (int) (new \DateTimeImmutable('+30 days'))->getTimestamp(),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => Request::capture()->isSecure(),
        ]);
    }

    private function userFromRememberCookie(): ?array
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $cookie, 2);
        $users = new UserRepository($this->app->db());
        $token = $users->findRememberToken($selector);

        if ($token === null || strtotime((string) $token['expires_at']) < time()) {
            $this->clearRememberCookie();

            return null;
        }

        if (!hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            // Possible token theft: drop every token for that user.
            $users->deleteRememberTokens((int) $token['user_id']);
            $this->clearRememberCookie();

            return null;
        }

        $user = $users->find((int) $token['user_id']);
        if ($user === null) {
            $this->clearRememberCookie();
        }

        return $user;
    }

    private function clearRememberCookie(): void
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            unset($_COOKIE[self::REMEMBER_COOKIE]);
            setcookie(self::REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }
}
