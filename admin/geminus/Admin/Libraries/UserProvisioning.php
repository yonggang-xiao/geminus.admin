<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use Closure;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Validation\ValidationInterface;
use Geminus\Admin\Entities\AdminUser;
use RuntimeException;
use Throwable;

class UserProvisioning
{
    private ?string $passwordHash = null;

    /**
     * @param Closure(): UserModel $users
     */
    public function __construct(
        private readonly Closure $users,
        private readonly UserIdentityModel $identities,
        private readonly ValidationInterface $validation,
        private readonly Passwords $passwords,
        private readonly array $usernameRules,
        private readonly array $emailRules,
        private readonly array $roles,
    ) {
    }

    public function create(string $username, string $email): string
    {
        $username = trim($username);
        $email    = strtolower(trim($email));

        if (! mb_check_encoding($username . $email, 'UTF-8')) {
            return 'invalid';
        }

        $validation = $this->validation;
        $validation->reset();
        $validation->setRules([
            'username' => $this->usernameRules,
            'email'    => $this->emailRules,
        ]);
        if (! $validation->run(['username' => $username, 'email' => $email])) {
            return 'invalid';
        }

        $users    = ($this->users)();
        $conflict = $this->conflict($users, $username, $email);
        if ($conflict !== null) {
            return $conflict;
        }

        $db    = $users->db;
        $depth = $db->transDepth;

        try {
            if (! $db->transBegin()) {
                throw new RuntimeException('User transaction could not be started.');
            }
            $user = new AdminUser(['username' => $username]);
            if (! $users->save($user)) {
                throw new RuntimeException('User could not be saved.');
            }
            $created      = $users->findById($users->getInsertID());
            $passwordHash = $this->passwordHash();
            $this->identities->create([
                'user_id' => $created->id,
                'type'    => Session::ID_TYPE_EMAIL_PASSWORD,
                'secret'  => $email,
                'secret2' => $passwordHash,
            ]);
            $users->addToDefaultGroup($created);
            if ($db->transStatus() === false) {
                throw new RuntimeException('User could not be saved.');
            }

            if (! $db->transCommit()) {
                throw new RuntimeException('User transaction could not be committed.');
            }

            return 'created';
        } catch (Throwable $exception) {
            $this->rollback($db, $depth);
            log_message('error', 'User provisioning failed: {type} ({code})', ['type' => $exception::class, 'code' => $exception->getCode()]);

            return 'save';
        }
    }

    /**
     * The caller must authorize the target and role before calling this method.
     * Returns updated, invalid, username, duplicate or save.
     * A caller owning an outer transaction must roll it back on save.
     */
    public function updateAccount(User $user, string $username, string $email, string $role, string $status): string
    {
        $users = ($this->users)();
        $user  = $user->id === null ? null : $users->findById($user->id);
        if ($user === null) {
            return 'invalid';
        }

        $username = trim($username);
        $email    = strtolower(trim($email));
        if (! mb_check_encoding($username . $email, 'UTF-8') || $role === 'superadmin' || ! in_array($role, $this->roles, true) || ! in_array($status, ['enabled', 'banned'], true)) {
            return 'invalid';
        }

        $usernameRules = $this->usernameRules;
        if ($username === $user->username) {
            $usernameRules['rules'] = ['required'];
        }
        $this->validation->reset();
        $this->validation->setRules(['username' => $usernameRules, 'email' => $this->emailRules]);
        if (! $this->validation->run(['username' => $username, 'email' => $email])) {
            return 'invalid';
        }

        $conflict = $this->conflict($users, $username, $email, $user->id, $user->username);
        if ($conflict !== null) {
            return $conflict;
        }

        $db    = $users->db;
        $depth = $db->transDepth;

        try {
            if (! $db->transBegin()) {
                throw new RuntimeException('User transaction could not be started.');
            }
            if ($username !== $user->username || $email !== $user->email) {
                $user->username = $username;
                $user->email    = $email;
                if (! $users->save($user)) {
                    throw new RuntimeException('User could not be updated.');
                }
                $user = $users->findById($user->id);
            }
            $user->syncGroups($role);
            if ($status === 'banned' && ! $user->isBanned()) {
                $user->status         = 'banned';
                $user->status_message = null;
                if (! $users->save($user)) {
                    throw new RuntimeException('User status could not be updated.');
                }
            } elseif ($status === 'enabled' && $user->isBanned()) {
                $user->status         = null;
                $user->status_message = null;
                if (! $users->save($user)) {
                    throw new RuntimeException('User status could not be updated.');
                }
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('User could not be updated.');
            }
            if (! $db->transCommit()) {
                throw new RuntimeException('User transaction could not be committed.');
            }

            return 'updated';
        } catch (Throwable $exception) {
            $this->rollback($db, $depth);
            log_message('error', 'User update failed: {type} ({code})', ['type' => $exception::class, 'code' => $exception->getCode()]);

            return 'save';
        }
    }

    public function usernameTaken(string $username, ?int $excludeId = null): bool
    {
        $users = ($this->users)();
        if ($excludeId !== null) {
            $users->where('id !=', $excludeId);
        }

        return $users->findByCredentials(['username' => $username]) !== null;
    }

    private function conflict(UserModel $users, string $username, string $email, ?int $excludeId = null, ?string $currentUsername = null): ?string
    {
        $existing = $users->findByCredentials(['email' => $email]);
        if ($existing !== null && $existing->id !== $excludeId) {
            return 'duplicate';
        }

        if ($username === $currentUsername) {
            return null;
        }

        if ($this->usernameTaken($username, $excludeId)) {
            return 'username';
        }

        return null;
    }

    private function passwordHash(): string
    {
        return $this->passwordHash ??= $this->passwords->hash(bin2hex(random_bytes(32)));
    }

    private function rollback(BaseConnection $db, int $depth): void
    {
        if ($db->transDepth > $depth) {
            $db->transRollback();
        }
        if ($db->transDepth === 0) {
            $db->resetTransStatus();
        }
    }
}
