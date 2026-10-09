<?php

if ($enabled) {
    echo strtr(lang('Admin.userInviteMicrosoftBody', [], $locale), [
        '{microsoftLink}' => esc(url_to('microsoft/start', $locale)),
    ]);
}
