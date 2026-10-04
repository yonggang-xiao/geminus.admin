<?php

use Composer\InstalledVersions;

$translations = require InstalledVersions::getInstallPath('codeigniter4/translations') . '/Language/zh-TW/Validation.php';

unset($translations['valid_cc_num']);

return array_replace($translations, [
    'field_exists'    => '{field}必須存在。',
    'valid_json'      => '{field}必須是有效的 JSON。',
    'valid_cc_number' => '{field}必須是有效的信用卡號。',
    'min_dims'        => '{field}不是圖片，或圖片尺寸過小。',
    'required'        => '請填寫{field}。',
    'matches'         => '{field}與{param}不一致。',
    'valid_date'      => '{field}必須是有效的日期。',
]);
