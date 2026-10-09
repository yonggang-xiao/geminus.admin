<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Geminus\Admin\Models\MicrosoftLinkRequestModel;

class MicrosoftLinks
{
    public const IDENTITY_TYPE = 'microsoft_entra';

    public function __construct(
        private MicrosoftLinkRequestModel $requests,
        private UserIdentityModel $identities,
        private UserModel $users,
        private BaseConnection $db,
    ) {
    }

    public function isEligible(User $user): bool
    {
        return ! $user->isBanned();
    }

    public function findUser(string $tenant, string $object): ?User
    {
        $identity = $this->identities->getIdentityBySecret(self::IDENTITY_TYPE, $this->key($tenant, $object));

        return $identity ? $this->users->findById($identity->user_id) : null;
    }

    public function request(string $tenant, string $object, ?string $email): bool
    {
        if ($this->findUser($tenant, $object)) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        if (! $this->requests->removeExpired($now)) {
            return false;
        }

        $data = [
            'tenant_id'  => strtolower($tenant),
            'object_id'  => strtolower($object),
            'email'      => $email === null ? null : mb_substr($email, 0, 255),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at' => $now,
        ];
        $existing = $this->requests->findForIdentity($data['tenant_id'], $data['object_id']);

        if ($existing) {
            if ($existing['status'] === 'rejected') {
                return false;
            }

            return $this->requests->update($existing['id'], $data);
        }

        if ($this->requests->countPending() >= 100
            || $this->requests->countPending($data['tenant_id']) >= 10) {
            return false;
        }

        $data['status'] = 'pending';

        return $this->requests->insert($data) !== false;
    }

    public function pending(): array
    {
        return $this->requests->pending(gmdate('Y-m-d H:i:s'));
    }

    public function reject(int $requestId): bool
    {
        return $this->requests->rejectPending($requestId, gmdate('Y-m-d H:i:s'));
    }

    public function revoke(User $user): bool
    {
        if (! $this->identities->getIdentityByType($user, self::IDENTITY_TYPE)) {
            return false;
        }

        $this->identities->deleteIdentitiesByType($user, self::IDENTITY_TYPE);

        return true;
    }

    public function approve(int $requestId, User $user): bool
    {
        $db = $this->db;
        if (! $db->transBegin()) {
            return false;
        }

        try {
            $request = $this->requests->findPending($requestId, gmdate('Y-m-d H:i:s'));
            if (! $request || ! $this->isEligible($user) || $this->findUser($request['tenant_id'], $request['object_id'])
                           || $this->identities->getIdentityByType($user, self::IDENTITY_TYPE)) {
                $db->transRollback();

                return false;
            }

            $inserted = $this->identities->insert([
                'user_id' => $user->id,
                'type'    => self::IDENTITY_TYPE,
                'secret'  => $this->key($request['tenant_id'], $request['object_id']),
            ]);
            if ($inserted === false || ! $this->requests->delete($requestId) || ! $db->transStatus() || ! $db->transCommit()) {
                $this->rollback();

                return false;
            }

            return true;
        } catch (DatabaseException $exception) {
            $this->rollback();

            return false;
        }
    }

    public function bind(User $user, string $tenant, string $object): bool
    {
        if (! $this->isEligible($user) || $this->findUser($tenant, $object)) {
            return false;
        }

        $db = $this->db;
        if (! $db->transBegin()) {
            return false;
        }

        try {
            if ($this->identities->getIdentityByType($user, self::IDENTITY_TYPE)) {
                $db->transRollback();

                return false;
            }

            $inserted = $this->identities->insert([
                'user_id' => $user->id,
                'type'    => self::IDENTITY_TYPE,
                'secret'  => $this->key($tenant, $object),
            ]);
            if ($inserted === false || ! $this->requests->removeForIdentity(strtolower($tenant), strtolower($object)) || ! $db->transStatus() || ! $db->transCommit()) {
                $this->rollback();

                return false;
            }

            return true;
        } catch (DatabaseException $exception) {
            $this->rollback();

            return false;
        }
    }

    private function rollback(): void
    {
        if ($this->db->transDepth > 0) {
            $this->db->transRollback();
        }

        if ($this->db->transDepth === 0) {
            $this->db->resetTransStatus();
        }
    }

    private function key(string $tenant, string $object): string
    {
        return strtolower($tenant . ':' . $object);
    }
}
