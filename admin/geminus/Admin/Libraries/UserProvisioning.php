<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use RuntimeException;
use Throwable;

class UserProvisioning
{
    private ?string $passwordHash = null;

    public function create(string $username, string $email): string
    {
        $username = trim($username);
        $email    = strtolower(trim($email));

        if (! mb_check_encoding($username . $email, 'UTF-8')) {
            return 'invalid';
        }

        $validation = Services::validation(null, false);
        $validation->setRules([
            'username' => config('Auth')->usernameValidationRules,
            'email'    => config('Auth')->emailValidationRules,
        ]);
        if (! $validation->run(['username' => $username, 'email' => $email])) {
            return 'invalid';
        }

        $users    = model(get_class(auth()->getProvider()), false);
        $conflict = $this->conflict($users, $username, $email);
        if ($conflict !== null) {
            return $conflict;
        }

        $db = $users->db;
        $db->transBegin();

        try {
            $user = new AdminUser(['username' => $username]);
            $users->save($user);
            $created      = $users->findById($users->getInsertID());
            $passwordHash = $this->passwordHash();
            model(UserIdentityModel::class)->create([
                'user_id' => $created->id,
                'type'    => Session::ID_TYPE_EMAIL_PASSWORD,
                'secret'  => $email,
                'secret2' => $passwordHash,
            ]);
            $users->addToDefaultGroup($created);
            if ($db->transStatus() === false) {
                throw new RuntimeException('User could not be saved.');
            }

            $db->transCommit();

            return 'created';
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'User provisioning failed: {type} ({code})', ['type' => $exception::class, 'code' => $exception->getCode()]);

            return 'save';
        }
    }

    public function updateAccount(User $user, string $username, string $email, string $role, string $status): string
    {
        $users    = model(get_class(auth()->getProvider()), false);
        $conflict = $this->conflict($users, $username, $email, $user->id, $user->username);
        if ($conflict !== null) {
            return $conflict;
        }

        $db = $users->db;
        $db->transBegin();

        try {
            if ($username !== $user->username || $email !== $user->email) {
                $user->username = $username;
                $user->email    = $email;
                $users->save($user);
                $user = $users->findById($user->id);
            }
            $user->syncGroups($role);
            if ($status === 'banned' && ! $user->isBanned()) {
                $user->ban();
            } elseif ($status === 'enabled' && $user->isBanned()) {
                $user->unBan();
            }
            if ($db->transStatus() === false) {
                throw new RuntimeException('User could not be updated.');
            }
            $db->transCommit();

            return 'updated';
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'User update failed: {type} ({code})', ['type' => $exception::class, 'code' => $exception->getCode()]);

            return 'save';
        }
    }

    public function usernameTaken(string $username, ?int $excludeId = null): bool
    {
        $users = model(get_class(auth()->getProvider()), false);
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
        return $this->passwordHash ??= service('passwords')->hash(bin2hex(random_bytes(32)));
    }
}
