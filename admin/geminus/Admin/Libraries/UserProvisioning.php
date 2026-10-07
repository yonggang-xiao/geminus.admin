<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Models\UserIdentityModel;
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

        $users = model(get_class(auth()->getProvider()), false);
        if ($users->findByCredentials(['email' => $email])) {
            return 'duplicate';
        }

        if ($users->where('username', $username)->first()) {
            return 'username';
        }

        $db = $users->db;
        $db->transBegin();

        try {
            $user = new AdminUser(['username' => $username]);
            $users->save($user);
            $created = $users->findById($users->getInsertID());
            $this->passwordHash ??= service('passwords')->hash(bin2hex(random_bytes(32)));
            model(UserIdentityModel::class)->insert([
                'user_id' => $created->id,
                'type'    => Session::ID_TYPE_EMAIL_PASSWORD,
                'secret'  => $email,
                'secret2' => $this->passwordHash,
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
}
