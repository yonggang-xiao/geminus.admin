<?php

$rendered = service('mailTemplates')->render('magic-link', service('request')->getLocale(), [
    'username'  => $user->username,
    'link'      => url_to('verify-magic-link') . '?token=' . rawurlencode($token),
    'ipAddress' => $ipAddress,
    'userAgent' => $userAgent,
    'date'      => $date,
]);
service('email')->setSubject($rendered['subject']);

echo view('Geminus\Admin\Views\auth\email\html', $rendered, ['debug' => false]);
