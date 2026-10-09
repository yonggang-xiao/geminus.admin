<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\Model;

class MicrosoftLinkRequestModel extends Model
{
    protected $table         = 'microsoft_link_requests';
    protected $returnType    = 'array';
    protected $allowedFields = ['tenant_id', 'object_id', 'email', 'status', 'expires_at', 'created_at'];

    public function removeExpired(string $now): bool
    {
        return $this->where('expires_at <=', $now)->delete();
    }

    public function findForIdentity(string $tenant, string $object): ?array
    {
        return $this->where('tenant_id', $tenant)->where('object_id', $object)->first();
    }

    public function countPending(?string $tenant = null): int
    {
        if ($tenant !== null) {
            $this->where('tenant_id', $tenant);
        }

        return $this->where('status', 'pending')->countAllResults();
    }

    public function pending(string $now): array
    {
        return $this->where('status', 'pending')->where('expires_at >', $now)->orderBy('created_at', 'DESC')->findAll();
    }

    public function findPending(int $requestId, string $now): ?array
    {
        return $this->where('status', 'pending')->where('expires_at >', $now)->find($requestId);
    }

    public function rejectPending(int $requestId, string $now): bool
    {
        return $this->where('status', 'pending')->where('expires_at >', $now)->update($requestId, ['status' => 'rejected'])
            && $this->db->affectedRows() === 1;
    }

    public function removeForIdentity(string $tenant, string $object): bool
    {
        return $this->where('tenant_id', $tenant)->where('object_id', $object)->delete();
    }
}
