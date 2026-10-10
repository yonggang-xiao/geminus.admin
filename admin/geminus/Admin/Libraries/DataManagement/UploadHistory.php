<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use CodeIgniter\Shield\Entities\User;
use Geminus\Admin\Models\AttachmentModel;
use InvalidArgumentException;

class UploadHistory
{
    public function __construct(private readonly AttachmentModel $attachments, private readonly array $sources)
    {
        foreach ($sources as $type => $source) {
            if (! is_string($type) || ! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $type) || ! $source instanceof UploadSource) {
                throw new InvalidArgumentException('Invalid upload history source.');
            }
        }
    }

    public function paginate(int $uploaderId, User $viewer, int $page = 1): array
    {
        if ($uploaderId < 1 || $page < 1) {
            throw new InvalidArgumentException('Invalid upload history request.');
        }

        $visibleSources   = [];
        $visibleResources = [];

        foreach ($this->sources as $type => $source) {
            $resources = $source->visibleResources($viewer);
            if ($resources === null) {
                continue;
            }

            $visibleSources[$type]   = $source;
            $visibleResources[$type] = $resources;
        }

        $items = $this->attachments->visibleUploadedBy($uploaderId, $visibleResources)->orderBy('id', 'DESC')->paginate(20, 'default', $page);

        foreach ($visibleSources as $type => $source) {
            $resourceIds = array_column(array_filter($items, static fn (array $item): bool => $item['resource_type'] === $type), 'resource_id');
            if ($resourceIds === []) {
                continue;
            }

            $descriptions = $source->describe(array_values(array_unique($resourceIds)));

            foreach ($items as $index => &$item) {
                if ($item['resource_type'] === $type) {
                    if (! isset($descriptions[$item['resource_id']])) {
                        unset($items[$index]);

                        continue;
                    }

                    $item['source'] = $descriptions[$item['resource_id']];
                }
            }
            unset($item);
        }

        return ['attachments' => array_values($items), 'pager' => $this->attachments->pager];
    }
}
