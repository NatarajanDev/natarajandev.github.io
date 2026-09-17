<?php

declare(strict_types=1);

namespace Cms\Repository;

/**
 * Users, login attempts and remember-me tokens.
 */
final class UserRepository extends Repository
{
    private const COLUMNS = 'id, name, email, role, status, bio, avatar, website, twitter, last_login_at, created_at, updated_at';

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null Includes the password hash — for authentication only. */
    public function findByEmail(string $email): ?array
    {
        return $this->db->first(
            'SELECT ' . self::COLUMNS . ', password_hash FROM users WHERE email = ?',
            [strtolower(trim($email))]
        );
    }

    /** @return array<string, mixed>|null */
    public function findWithHash(int $id): ?array
    {
        return $this->db->first('SELECT ' . self::COLUMNS . ', password_hash FROM users WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(string $orderBy = 'created_at DESC'): array
    {
        $allowed = ['created_at DESC', 'created_at ASC', 'name ASC', 'role ASC'];
        $order = in_array($orderBy, $allowed, true) ? $orderBy : 'created_at DESC';

        return $this->db->all(
            'SELECT u.id, u.name, u.email, u.role, u.status, u.avatar, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS post_count,
                    (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.status = ?) AS published_count
             FROM users u
             ORDER BY u.' . $order,
            ['published']
        );
    }

    /** Public author archive lookup by slugified display name. */
    public function findByNameSlug(string $slug): ?array
    {
        foreach ($this->db->all('SELECT ' . self::COLUMNS . ' FROM users WHERE status = ?', ['active']) as $user) {
            if (str_slug((string) $user['name']) === $slug) {
                return $user;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function authors(): array
    {
        return $this->db->all(
            'SELECT u.id, u.name, u.avatar, u.bio, COUNT(p.id) AS published_count
             FROM users u
             LEFT JOIN posts p ON p.user_id = u.id AND p.status = ?
             WHERE u.status = ?
             GROUP BY u.id, u.name, u.avatar, u.bio
             HAVING COUNT(p.id) > 0
             ORDER BY published_count DESC',
            ['published', 'active']
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = $this->now();

        return $this->db->insert('users', [
            'name' => (string) $data['name'],
            'email' => strtolower((string) $data['email']),
            'password_hash' => (string) $data['password_hash'],
            'role' => (string) ($data['role'] ?? 'author'),
            'status' => (string) ($data['status'] ?? 'active'),
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'website' => $data['website'] ?? null,
            'twitter' => $data['twitter'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = array_intersect_key($data, array_flip([
            'name', 'email', 'role', 'status', 'bio', 'avatar', 'website', 'twitter',
        ]));

        if ($fields === []) {
            return;
        }

        if (isset($fields['email'])) {
            $fields['email'] = strtolower((string) $fields['email']);
        }
        $fields['updated_at'] = $this->now();

        $this->db->update('users', $fields, 'id', $id);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->update('users', [
            'password_hash' => $hash,
            'updated_at' => $this->now(),
        ], 'id', $id);
    }

    public function touchLogin(int $id): void
    {
        $this->db->update('users', ['last_login_at' => $this->now()], 'id', $id);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function delete(int $id): void
    {
        $this->db->delete('users', 'id = ?', [$id]);
        $this->db->delete('remember_tokens', 'user_id = ?', [$id]);
    }

    public function countAdmins(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE role = ? AND status = ?', ['admin', 'active']);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) FROM users'),
            'active' => (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE status = ?', ['active']),
            'admins' => $this->countAdmins(),
            'new_this_month' => (int) $this->db->scalar(
                'SELECT COUNT(*) FROM users WHERE created_at >= ?',
                [(new \DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00')]
            ),
        ];
    }

    // ------------------------------------------------------------ login audit

    public function recordLoginAttempt(string $email, string $ip, bool $success): void
    {
        $this->db->insert('login_attempts', [
            'identifier' => strtolower(trim($email)),
            'ip' => $ip,
            'success' => $success ? 1 : 0,
            'created_at' => $this->now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function recentAttempts(int $limit = 25): array
    {
        return $this->db->all(
            'SELECT * FROM login_attempts ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit)
        );
    }

    public function pruneAttempts(int $days = 30): int
    {
        if ($days <= 0) {
            return $this->db->delete('login_attempts', '1 = 1');
        }

        return $this->db->delete(
            'login_attempts',
            'created_at < ?',
            [(new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s')]
        );
    }

    // ------------------------------------------------------------ remember me

    public function storeRememberToken(int $userId, string $selector, string $validatorHash, string $expiresAt): void
    {
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => $validatorHash,
            'expires_at' => $expiresAt,
            'created_at' => $this->now(),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findRememberToken(string $selector): ?array
    {
        return $this->db->first('SELECT * FROM remember_tokens WHERE selector = ?', [$selector]);
    }

    public function deleteRememberTokens(int $userId): void
    {
        $this->db->delete('remember_tokens', 'user_id = ?', [$userId]);
    }

    public function pruneRememberTokens(): int
    {
        return $this->db->delete('remember_tokens', 'expires_at < ?', [$this->now()]);
    }
}
