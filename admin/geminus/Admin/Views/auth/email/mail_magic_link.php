<?php

use Geminus\Admin\Libraries\MailTemplates;

echo (new MailTemplates())->renderShield('magic-link', [
    'username'  => $user->username,
    'link'      => url_to('verify-magic-link') . '?token=' . rawurlencode($token),
    'ipAddress' => $ipAddress,
    'userAgent' => $userAgent,
    'date'      => $date,
]);
