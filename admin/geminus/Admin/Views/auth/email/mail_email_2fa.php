<?php

$rendered = service('mailTemplates')->render('email-2fa', service('request')->getLocale(), [
    'username'  => $user->username,
    'code'      => $code,
    'ipAddress' => $ipAddress,
    'userAgent' => $userAgent,
    'date'      => $date,
]);
service('email')->setSubject($rendered['subject']);

echo view('Geminus\Admin\Views\auth\email\html', $rendered, ['debug' => false]);
