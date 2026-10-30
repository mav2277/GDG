<?php

declare(strict_types=1);

final class User
{
    public function __construct(private PDO $database)
    {
    }

    public function countAdminAccounts(): int
    {
        return (int) $this->database->query("SELECT COUNT(*) FROM admins WHERE role = 'admin' AND is_active = 1")->fetchColumn();
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, COALESCE(NULLIF(display_name, \'\'), username) AS display_name,
                    username, email, password_hash, role, is_active
             FROM admins
             WHERE username = :username OR email = :email
             LIMIT 1'
        );
        $statement->execute(['username' => $identifier, 'email' => $identifier]);
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->database->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id');
        $statement->execute(['password_hash' => $passwordHash, 'id' => $userId]);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPasswordHash): bool
    {
        $statement = $this->database->prepare('SELECT password_hash FROM admins WHERE id = :id AND is_active = 1 LIMIT 1');
        $statement->execute(['id' => $userId]);
        $currentHash = $statement->fetchColumn();
        if (!is_string($currentHash) || !password_verify($currentPassword, $currentHash)) {
            return false;
        }

        $update = $this->database->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id AND is_active = 1');
        $update->execute(['hash' => $newPasswordHash, 'id' => $userId]);
        return $update->rowCount() === 1;
    }

    /** @return array<int, array{id:int, display_name:string, username:string, email:?string, role:string, is_active:int, status:string}> */
    public function allAccounts(): array
    {
        return $this->database->query(
            'SELECT u.id, COALESCE(NULLIF(u.display_name, \'\'), u.username) AS display_name,
                    u.username, u.email, u.role, u.is_active, u.created_at,
                    (SELECT MAX(t.expires_at) FROM password_reset_tokens AS t
                     WHERE t.user_id = u.id AND t.used_at IS NULL) AS access_link_expires_at,
                    CASE
                      WHEN u.is_active = 1 THEN \'active\'
                      WHEN (SELECT MAX(t.expires_at) FROM password_reset_tokens AS t
                            WHERE t.user_id = u.id AND t.used_at IS NULL) IS NULL THEN \'disabled\'
                      WHEN (SELECT MAX(t.expires_at) FROM password_reset_tokens AS t
                            WHERE t.user_id = u.id AND t.used_at IS NULL) > CURRENT_TIMESTAMP THEN \'pending\'
                      ELSE \'expired\'
                    END AS account_status
             FROM admins AS u
             ORDER BY u.role, u.display_name, u.id'
        )->fetchAll();
    }

    public function createAccountAccessLink(int $targetUserId, string $tokenHash): bool
    {
        $this->database->beginTransaction();
        try {
            $lock = $this->database->prepare('SELECT id, is_active FROM admins WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute(['id' => $targetUserId]);
            $account = $lock->fetch();
            if ($account === false) {
                throw new OutOfBoundsException('That account no longer exists. Refresh the list and try again.');
            }

            $invalidate = $this->database->prepare(
                'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND used_at IS NULL'
            );
            $invalidate->execute(['user_id' => $targetUserId]);

            $insert = $this->database->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR))'
            );
            $insert->execute(['user_id' => $targetUserId, 'token_hash' => $tokenHash]);
            $this->database->commit();
            return (int) $account['is_active'] === 1;
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    public function deactivateAccount(int $targetUserId, int $actingUserId): void
    {
        if ($targetUserId === $actingUserId) {
            throw new DomainException('You cannot deactivate your own account.');
        }

        $this->database->beginTransaction();
        try {
            $lock = $this->database->prepare('SELECT role, is_active FROM admins WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute(['id' => $targetUserId]);
            $target = $lock->fetch();
            if ($target === false) {
                throw new OutOfBoundsException('That account no longer exists. Refresh the list and try again.');
            }
            if ((int) $target['is_active'] !== 1) {
                throw new DomainException('That account is already inactive. Refresh the list.');
            }

            if ($target['role'] === 'admin') {
                $activeAdmins = $this->database->query(
                    "SELECT id FROM admins WHERE role = 'admin' AND is_active = 1 ORDER BY id FOR UPDATE"
                )->fetchAll();
                if (count($activeAdmins) <= 1) {
                    throw new DomainException('The last active Admin account cannot be deactivated.');
                }
            }

            $deactivate = $this->database->prepare('UPDATE admins SET is_active = 0 WHERE id = :id AND is_active = 1');
            $deactivate->execute(['id' => $targetUserId]);
            $revokeLinks = $this->database->prepare(
                'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND used_at IS NULL'
            );
            $revokeLinks->execute(['user_id' => $targetUserId]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    public function accountAccessByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->database->prepare(
            'SELECT t.id AS token_id, u.id,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS display_name,
                    u.username, u.email, u.role, u.is_active
             FROM password_reset_tokens AS t
             INNER JOIN admins AS u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash AND t.used_at IS NULL
               AND t.expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $account = $statement->fetch();
        return $account === false ? null : $account;
    }

    public function completeAccountAccess(
        string $tokenHash,
        ?string $displayName,
        ?string $username,
        string $passwordHash
    ): ?array {
        $this->database->beginTransaction();
        try {
            $find = $this->database->prepare(
                'SELECT t.id AS token_id, u.id,
                        COALESCE(NULLIF(u.display_name, \'\'), u.username) AS display_name,
                        u.username, u.email, u.role, u.is_active
                 FROM password_reset_tokens AS t
                 INNER JOIN admins AS u ON u.id = t.user_id
                 WHERE t.token_hash = :token_hash AND t.used_at IS NULL
                   AND t.expires_at > CURRENT_TIMESTAMP
                 LIMIT 1 FOR UPDATE'
            );
            $find->execute(['token_hash' => $tokenHash]);
            $account = $find->fetch();
            if ($account === false) {
                $this->database->rollBack();
                return null;
            }

            if ((int) $account['is_active'] === 1) {
                $update = $this->database->prepare(
                    'UPDATE admins SET password_hash = :password_hash WHERE id = :id AND is_active = 1'
                );
                $update->execute(['password_hash' => $passwordHash, 'id' => $account['id']]);
            } else {
                if ($displayName === null || $username === null) {
                    throw new DomainException('Enter the account name and username to activate this account.');
                }
                $update = $this->database->prepare(
                    'UPDATE admins SET display_name = :display_name, username = :username,
                        password_hash = :password_hash, is_active = 1 WHERE id = :id AND is_active = 0'
                );
                $update->execute([
                    'display_name' => $displayName,
                    'username' => $username,
                    'password_hash' => $passwordHash,
                    'id' => $account['id'],
                ]);
                $account['display_name'] = $displayName;
                $account['username'] = $username;
            }

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The account could not be updated.');
            }

            $useToken = $this->database->prepare(
                'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = :id AND used_at IS NULL'
            );
            $useToken->execute(['id' => $account['token_id']]);
            if ($useToken->rowCount() !== 1) {
                throw new RuntimeException('The account access link was already used.');
            }
            $this->database->commit();

            return [
                'id' => (int) $account['id'],
                'display_name' => $account['display_name'],
                'username' => $account['username'],
                'email' => $account['email'],
                'role' => $account['role'],
            ];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    public function createOrRenewInvitation(string $email, string $role, int $invitedBy, string $tokenHash): void
    {
        $this->database->beginTransaction();
        try {
            $find = $this->database->prepare(
                'SELECT id, role, is_active FROM admins WHERE email = :email LIMIT 1 FOR UPDATE'
            );
            $find->execute(['email' => $email]);
            $existing = $find->fetch();

            if ($existing !== false) {
                if ((int) $existing['is_active'] === 1) {
                    throw new DomainException('An active account already uses that email address.');
                }
                if ($existing['role'] !== $role) {
                    throw new DomainException('That email already has a pending account with a different role.');
                }

                $pending = $this->database->prepare(
                    'SELECT id FROM password_reset_tokens WHERE user_id = :user_id AND used_at IS NULL LIMIT 1'
                );
                $pending->execute(['user_id' => $existing['id']]);
                if ($pending->fetchColumn() === false) {
                    throw new DomainException('An inactive account already uses that email. Ask an Admin to review it.');
                }

                $userId = (int) $existing['id'];
                $invalidate = $this->database->prepare(
                    'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND used_at IS NULL'
                );
                $invalidate->execute(['user_id' => $userId]);
            } else {
                $insertUser = $this->database->prepare(
                    'INSERT INTO admins (display_name, username, email, password_hash, role, is_active, created_by_user_id)
                     VALUES (:display_name, :username, :email, :password_hash, :role, 0, :created_by)'
                );
                $insertUser->execute([
                    'display_name' => 'Pending invitation',
                    'username' => 'invite-' . bin2hex(random_bytes(12)),
                    'email' => $email,
                    'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'role' => $role,
                    'created_by' => $invitedBy,
                ]);
                $userId = (int) $this->database->lastInsertId();
            }

            $insertToken = $this->database->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR))'
            );
            $insertToken->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    public function createInitialAdmin(string $username, string $email, string $passwordHash): int
    {
        $statement = $this->database->prepare(
            "INSERT INTO admins (display_name, username, email, password_hash, role, is_active)
             VALUES (:display_name, :username, :email, :password_hash, 'admin', 1)"
        );
        $statement->execute([
            'display_name' => $username,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
        return (int) $this->database->lastInsertId();
    }

    public function findActiveById(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, COALESCE(NULLIF(display_name, \'\'), username) AS display_name,
                    username, email, role, is_active
             FROM admins WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }
}
