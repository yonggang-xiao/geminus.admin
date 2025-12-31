<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

return [
    // Exceptions
    'unknownAuthenticator'  => '{0} 不是有效的驗證器。',
    'unknownUserProvider'   => '無法判斷要使用的使用者提供者。',
    'invalidUser'           => '無法找到指定的使用者。',
    'bannedUser'            => '由於你目前已被封禁，無法登入。',
    'logOutBannedUser'      => '由於你已被封禁，已將你登出。',
    'badAttempt'            => '無法登入，請檢查你的登入資訊。',
    'noPassword'            => '沒有密碼無法驗證使用者。',
    'invalidPassword'       => '無法登入，請檢查你的密碼。',
    'noToken'               => '每個請求都必須在 {0} 標頭中攜帶 Bearer 權杖。',
    'badToken'              => '存取權杖無效。',
    'oldToken'              => '存取權杖已過期。',
    'noUserEntity'          => '驗證密碼時必須提供使用者實體。',
    'invalidEmail'          => '無法驗證電子郵件地址「{0}」與紀錄中的郵件一致。',
    'unableSendEmailToUser' => '抱歉，寄送電子郵件時發生問題，無法寄送到「{0}」。',
    'throttled'             => '來自此 IP 的請求過多，請在 {0} 秒後再試。',
    'notEnoughPrivilege'    => '你沒有執行此操作所需的權限。',
    // JWT Exceptions
    'invalidJWT'     => '權杖無效。',
    'expiredJWT'     => '權杖已過期。',
    'beforeValidJWT' => '權杖尚未生效。',

    'email'           => '電子郵件地址',
    'username'        => '使用者名稱',
    'password'        => '密碼',
    'passwordConfirm' => '密碼（再次輸入）',
    'haveAccount'     => '已經有帳號？',
    'token'           => '權杖',

    // Buttons
    'confirm' => '確認',
    'send'    => '送出',

    // Registration
    'register'         => '註冊',
    'registerDisabled' => '目前不允許註冊。',
    'registerSuccess'  => '歡迎加入！',

    // Login
    'login'              => '登入',
    'needAccount'        => '需要帳號？',
    'rememberMe'         => '記住我？',
    'forgotPassword'     => '忘記密碼？',
    'useMagicLink'       => '使用登入連結',
    'magicLinkSubject'   => '你的登入連結',
    'magicTokenNotFound' => '無法驗證此連結。',
    'magicLinkExpired'   => '抱歉，連結已過期。',
    'checkYourEmail'     => '請檢查你的電子郵件！',
    'magicLinkDetails'   => '我們剛寄出一封包含登入連結的郵件，該連結僅在 {0} 分鐘內有效。',
    'magicLinkDisabled'  => '目前不允許使用 MagicLink。',
    'successLogout'      => '你已成功登出。',
    'backToLogin'        => '返回登入',

    // Passwords
    'errorPasswordLength'       => '密碼長度至少需要 {0, number} 個字元。',
    'suggestPasswordLength'     => '使用密碼片語（最長 255 個字元）可建立更安全且更易記的密碼。',
    'errorPasswordCommon'       => '密碼不能是常見密碼。',
    'suggestPasswordCommon'     => '此密碼已與超過 6.5 萬個常用密碼及外洩密碼清單進行比對。',
    'errorPasswordPersonal'     => '密碼不能包含重新雜湊後的個人資訊。',
    'suggestPasswordPersonal'   => '請勿使用你的電子郵件或使用者名稱的變體作為密碼。',
    'errorPasswordTooSimilar'   => '密碼與使用者名稱過於相似。',
    'suggestPasswordTooSimilar' => '不要在密碼中使用使用者名稱的部分內容。',
    'errorPasswordPwned'        => '由於資料外洩，密碼 {0} 已被曝光，並在 {2} 份外洩密碼集中出現了 {1, number} 次。',
    'suggestPasswordPwned'      => '絕不應將 {0} 用作密碼。若你在任何地方使用了它，請立刻更改。',
    'errorPasswordEmpty'        => '必須填寫密碼。',
    'errorPasswordTooLongBytes' => '密碼長度不可超過 {param} 位元組。',
    'passwordChangeSuccess'     => '密碼已成功變更',
    'userDoesNotExist'          => '密碼未變更：使用者不存在',
    'resetTokenExpired'         => '抱歉，你的重設權杖已過期。',

    // Email Globals
    'emailInfo'      => '關於此使用者的一些資訊：',
    'emailIpAddress' => 'IP 位址：',
    'emailDevice'    => '裝置：',
    'emailDate'      => '日期：',

    // 2FA
    'email2FATitle'       => '雙因素驗證',
    'confirmEmailAddress' => '確認你的電子郵件地址。',
    'emailEnterCode'      => '確認你的電子郵件',
    'emailConfirmCode'    => '請輸入我們剛寄到你電子郵件的 6 位數驗證碼。',
    'email2FASubject'     => '你的驗證碼',
    'email2FAMailBody'    => '你的驗證碼是：',
    'invalid2FAToken'     => '驗證碼不正確。',
    'need2FA'             => '你必須完成雙因素驗證。',
    'needVerification'    => '請檢查電子郵件以完成帳號啟用。',

    // Activate
    'emailActivateTitle'    => '電子郵件啟用',
    'emailActivateBody'     => '我們剛寄出一封包含驗證碼的郵件，用於確認你的電子郵件地址。請複製該驗證碼並貼到下方。',
    'emailActivateSubject'  => '你的啟用碼',
    'emailActivateMailBody' => '請使用下方驗證碼啟用你的帳號並開始使用本站。',
    'invalidActivateToken'  => '驗證碼不正確。',
    'needActivate'          => '你必須透過確認寄到電子郵件的驗證碼來完成註冊。',
    'activationBlocked'     => '登入前必須先啟用你的帳號。',

    // Groups
    'unknownGroup' => '{0} 不是有效的群組。',
    'missingTitle' => '群組必須有標題。',

    // Permissions
    'unknownPermission' => '{0} 不是有效的權限。',
];
