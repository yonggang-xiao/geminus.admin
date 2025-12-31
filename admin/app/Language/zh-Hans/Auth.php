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
    'unknownAuthenticator'  => '{0} 不是有效的认证器。',
    'unknownUserProvider'   => '无法确定要使用的用户提供器。',
    'invalidUser'           => '无法找到指定的用户。',
    'bannedUser'            => '由于你当前被封禁，无法登录。',
    'logOutBannedUser'      => '由于你已被封禁，已将你登出。',
    'badAttempt'            => '无法登录，请检查你的登录信息。',
    'noPassword'            => '没有密码无法验证用户。',
    'invalidPassword'       => '无法登录，请检查你的密码。',
    'noToken'               => '每个请求都必须在 {0} 请求头中携带 Bearer 令牌。',
    'badToken'              => '访问令牌无效。',
    'oldToken'              => '访问令牌已过期。',
    'noUserEntity'          => '验证密码时必须提供用户实体。',
    'invalidEmail'          => '无法验证邮箱地址“{0}”与记录中的邮箱一致。',
    'unableSendEmailToUser' => '抱歉，发送邮件时出现问题，无法发送到“{0}”。',
    'throttled'             => '来自该 IP 的请求过多，请在 {0} 秒后重试。',
    'notEnoughPrivilege'    => '你没有执行该操作所需的权限。',
    // JWT Exceptions
    'invalidJWT'     => '令牌无效。',
    'expiredJWT'     => '令牌已过期。',
    'beforeValidJWT' => '令牌尚未生效。',

    'email'           => '邮箱地址',
    'username'        => '用户名',
    'password'        => '密码',
    'passwordConfirm' => '密码（再次输入）',
    'haveAccount'     => '已有账号？',
    'token'           => '令牌',

    // Buttons
    'confirm' => '确认',
    'send'    => '发送',

    // Registration
    'register'         => '注册',
    'registerDisabled' => '当前不允许注册。',
    'registerSuccess'  => '欢迎加入！',

    // Login
    'login'              => '登录',
    'needAccount'        => '需要账号？',
    'rememberMe'         => '记住我？',
    'forgotPassword'     => '忘记密码？',
    'useMagicLink'       => '使用登录链接',
    'magicLinkSubject'   => '你的登录链接',
    'magicTokenNotFound' => '无法验证该链接。',
    'magicLinkExpired'   => '抱歉，链接已过期。',
    'checkYourEmail'     => '请检查你的邮箱！',
    'magicLinkDetails'   => '我们刚向你发送了一封包含登录链接的邮件，该链接仅在 {0} 分钟内有效。',
    'magicLinkDisabled'  => '当前不允许使用 MagicLink。',
    'successLogout'      => '你已成功退出登录。',
    'backToLogin'        => '返回登录',

    // Passwords
    'errorPasswordLength'       => '密码长度至少需要 {0, number} 个字符。',
    'suggestPasswordLength'     => '使用密码短语（最长 255 个字符）可以生成更安全且更易记的密码。',
    'errorPasswordCommon'       => '密码不能是常见密码。',
    'suggestPasswordCommon'     => '该密码已与超过 6.5 万个常用密码及泄露密码列表进行比对。',
    'errorPasswordPersonal'     => '密码不能包含重新哈希后的个人信息。',
    'suggestPasswordPersonal'   => '不要使用你的邮箱地址或用户名的变体作为密码。',
    'errorPasswordTooSimilar'   => '密码与用户名过于相似。',
    'suggestPasswordTooSimilar' => '不要在密码中使用用户名的部分内容。',
    'errorPasswordPwned'        => '由于数据泄露，密码 {0} 已被暴露，并在 {2} 份泄露密码集中出现了 {1, number} 次。',
    'suggestPasswordPwned'      => '绝不应将 {0} 用作密码。如果你在其他地方使用了它，请立即更改。',
    'errorPasswordEmpty'        => '必须填写密码。',
    'errorPasswordTooLongBytes' => '密码长度不能超过 {param} 字节。',
    'passwordChangeSuccess'     => '密码修改成功',
    'userDoesNotExist'          => '密码未修改：用户不存在',
    'resetTokenExpired'         => '抱歉，你的重置令牌已过期。',

    // Email Globals
    'emailInfo'      => '关于该用户的一些信息：',
    'emailIpAddress' => 'IP 地址：',
    'emailDevice'    => '设备：',
    'emailDate'      => '日期：',

    // 2FA
    'email2FATitle'       => '双因素认证',
    'confirmEmailAddress' => '确认你的邮箱地址。',
    'emailEnterCode'      => '确认你的邮箱',
    'emailConfirmCode'    => '请输入我们刚发送到你邮箱的 6 位验证码。',
    'email2FASubject'     => '你的验证码',
    'email2FAMailBody'    => '你的验证码是：',
    'invalid2FAToken'     => '验证码不正确。',
    'need2FA'             => '你必须完成双因素验证。',
    'needVerification'    => '请检查邮箱以完成账号激活。',

    // Activate
    'emailActivateTitle'    => '邮箱激活',
    'emailActivateBody'     => '我们刚向你发送了一封包含验证码的邮件，用于确认你的邮箱地址。请复制该验证码并粘贴到下方。',
    'emailActivateSubject'  => '你的激活码',
    'emailActivateMailBody' => '请使用下方验证码激活你的账号并开始使用本站。',
    'invalidActivateToken'  => '验证码不正确。',
    'needActivate'          => '你必须通过确认发送到邮箱的验证码来完成注册。',
    'activationBlocked'     => '登录前必须先激活你的账号。',

    // Groups
    'unknownGroup' => '{0} 不是有效的用户组。',
    'missingTitle' => '用户组必须有标题。',

    // Permissions
    'unknownPermission' => '{0} 不是有效的权限。',
];
