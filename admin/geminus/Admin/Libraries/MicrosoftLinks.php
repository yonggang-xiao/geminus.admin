<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Database;

class MicrosoftLinks
{
    public const IDENTITY_TYPE = 'microsoft_entra';

    public function isEligible(User $user): bool
    {
        return ! $user->isBanned();
    }

    public function findUser(string $tenant, string $object): ?User
    {
        $identity = model(UserIdentityModel::class)->getIdentityBySecret(self::IDENTITY_TYPE, $this->key($tenant, $object));

        return $identity ? auth()->getProvider()->findById($identity->user_id) : null;
    }

    public function request(string $tenant, string $object, ?string $email): bool
    {
        if ($this->findUser($tenant, $object)) {
            return false;
        }

        $db = Database::connect();
        $db->table('microsoft_link_requests')->where('expires_at <=', gmdate('Y-m-d H:i:s'))->delete();
        $data = [
            'tenant_id'  => strtolower($tenant),
            'object_id'  => strtolower($object),
            'email'      => $email === null ? null : mb_substr($email, 0, 255),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        $existing = $db->table('microsoft_link_requests')->where('tenant_id', $data['tenant_id'])->where('object_id', $data['object_id'])->get()->getRowArray();

        if ($existing) {
            if ($existing['status'] === 'rejected') {
                return false;
            }

            $db->table('microsoft_link_requests')->where('id', $existing['id'])->update($data);

            return true;
        }

        if ($db->table('microsoft_link_requests')->where('status', 'pending')->countAllResults() >= 100
            || $db->table('microsoft_link_requests')->where('status', 'pending')->where('tenant_id', $data['tenant_id'])->countAllResults() >= 10) {
            return false;
        }

        $data['status'] = 'pending';
        $db->table('microsoft_link_requests')->insert($data);

        return true;
    }

    public function pending(): array
    {
        return Database::connect()->table('microsoft_link_requests')->where('status', 'pending')->where('expires_at >', gmdate('Y-m-d H:i:s'))->orderBy('created_at', 'DESC')->get()->getResultArray();
    }

    public function reject(int $requestId): bool
    {
        $db = Database::connect();
        $db->table('microsoft_link_requests')
            ->where('id', $requestId)->where('status', 'pending')->where('expires_at >', gmdate('Y-m-d H:i:s'))
            ->update(['status' => 'rejected']);

        return $db->affectedRows() === 1;
    }

    public function revoke(User $user): bool
    {
        $identities = model(UserIdentityModel::class);
        if (! $identities->getIdentityByType($user, self::IDENTITY_TYPE)) {
            return false;
        }

        $identities->deleteIdentitiesByType($user, self::IDENTITY_TYPE);

        return true;
    }

    public function approve(int $requestId, User $user): bool
    {
        $db = Database::connect();
        $db->transBegin();

        try {
            $request = $db->table('microsoft_link_requests')->where('id', $requestId)->where('status', 'pending')->where('expires_at >', gmdate('Y-m-d H:i:s'))->get()->getRowArray();
            if (! $request || ! $this->isEligible($user) || $this->findUser($request['tenant_id'], $request['object_id'])
                           || model(UserIdentityModel::class)->getIdentityByType($user, self::IDENTITY_TYPE)) {
                $db->transRollback();

                return false;
            }

            $identity = model(UserIdentityModel::class);
            $identity->insert([
                'user_id' => $user->id,
                'type'    => self::IDENTITY_TYPE,
                'secret'  => $this->key($request['tenant_id'], $request['object_id']),
            ]);
            $db->table('microsoft_link_requests')->where('id', $requestId)->delete();
            $db->transCommit();

            return true;
        } catch (DatabaseException $exception) {
            $db->transRollback();

            return false;
        }
    }

    public function bind(User $user, string $tenant, string $object): bool
    {
        if (! $this->isEligible($user) || $this->findUser($tenant, $object)) {
            return false;
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            if (model(UserIdentityModel::class)->getIdentityByType($user, self::IDENTITY_TYPE)) {
                $db->transRollback();

                return false;
            }

            model(UserIdentityModel::class)->insert([
                'user_id' => $user->id,
                'type'    => self::IDENTITY_TYPE,
                'secret'  => $this->key($tenant, $object),
            ]);
            $db->table('microsoft_link_requests')->where('tenant_id', strtolower($tenant))->where('object_id', strtolower($object))->delete();
            $db->transCommit();

            return true;
        } catch (DatabaseException $exception) {
            $db->transRollback();

            return false;
        }
    }

    private function key(string $tenant, string $object): string
    {
        return strtolower($tenant . ':' . $object);
    }
}
