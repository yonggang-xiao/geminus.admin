<?php

declare(strict_types=1);

namespace Modules\Announcements\Models;

use CodeIgniter\Model;

class AnnouncementModel extends Model
{
    protected $table         = 'example_announcements';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['title', 'body'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';
}
