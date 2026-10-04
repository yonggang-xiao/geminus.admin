<?php

use Composer\InstalledVersions;

$translations = require InstalledVersions::getInstallPath('codeigniter4/translations') . '/Language/zh-CN/Validation.php';

unset($translations['valid_cc_num']);

return array_replace($translations, [
    'field_exists'    => '{field}必须存在。',
    'valid_json'      => '{field}必须是有效的 JSON。',
    'valid_cc_number' => '{field}必须是有效的信用卡号。',
    'min_dims'        => '{field}不是图片，或图片尺寸过小。',
    'required'        => '请填写{field}。',
    'matches'         => '{field}与{param}不一致。',
    'valid_date'      => '{field}必须是有效的日期。',
]);
