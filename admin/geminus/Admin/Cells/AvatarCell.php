<?php

namespace Geminus\Admin\Cells;

use CodeIgniter\View\Cells\Cell;
use Geminus\Admin\Entities\AdminUser;

class AvatarCell extends Cell
{
    public ?AdminUser $user = null;
}
