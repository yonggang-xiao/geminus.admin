<?php

use Geminus\Admin\Libraries\MailTemplates;

echo (new MailTemplates())->renderShield('email-2fa', [
    'username'  => $user->username,
    'code'      => $code,
    'ipAddress' => $ipAddress,
    'userAgent' => $userAgent,
    'date'      => $date,
]);
