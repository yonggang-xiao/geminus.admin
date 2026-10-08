<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use InvalidArgumentException;

class MailTemplates
{
    public const TYPES = [
        'invitation' => ['label' => 'mailInvitation', 'html' => true, 'required' => 'link', 'variables' => ['username', 'link', 'microsoftLogin'], 'subjectVariables' => ['username']],
        'magic-link' => ['label' => 'mailMagicLink', 'html' => true, 'required' => 'link', 'variables' => ['username', 'link', 'ipAddress', 'userAgent', 'date'], 'subjectVariables' => ['username', 'date']],
        'activation' => ['label' => 'mailActivation', 'html' => true, 'required' => 'code', 'variables' => ['username', 'code', 'ipAddress', 'userAgent', 'date'], 'subjectVariables' => ['username', 'date']],
        'email-2fa'  => ['label' => 'mailEmail2fa', 'html' => true, 'required' => 'code', 'variables' => ['username', 'code', 'ipAddress', 'userAgent', 'date'], 'subjectVariables' => ['username', 'date']],
    ];

    public function get(string $type, string $locale): array
    {
        if (! isset(self::TYPES[$type]) || ! in_array($locale, config('App')->supportedLocales, true)) {
            throw new InvalidArgumentException('Unknown mail template or locale.');
        }

        return [
            'subject' => service('settings')->get(self::settingKey($type, $locale, 'subject')) ?? lang($this->languageKey($type, 'Subject'), [], $locale),
            'body'    => service('settings')->get(self::settingKey($type, $locale, 'body')) ?? str_replace(['{0}', '{1}'], ['{username}', '{link}'], lang($this->languageKey($type, 'Body'), [], $locale)),
        ];
    }

    public function render(string $type, string $locale, array $values): array
    {
        $template            = $this->get($type, $locale);
        $subjectReplacements = [];
        $bodyReplacements    = [];

        foreach (self::TYPES[$type]['variables'] as $name) {
            $value                                  = (string) ($values[$name] ?? '');
            $subjectReplacements['{' . $name . '}'] = $value;
            $bodyReplacements['{' . $name . '}']    = self::TYPES[$type]['html'] ? esc($value) : $value;
        }

        if ($type === 'invitation') {
            $bodyReplacements['{microsoftLogin}'] = $this->microsoftLoginBody($locale);
        }

        return [
            'subject' => strtr($template['subject'], $subjectReplacements),
            'body'    => strtr($template['body'], $bodyReplacements),
        ];
    }

    public function renderShield(string $type, array $values): string
    {
        $rendered = $this->render($type, service('request')->getLocale(), $values);
        service('email')->setSubject($rendered['subject']);

        return $this->renderHtml($rendered);
    }

    public function microsoftLoginBody(string $locale): string
    {
        if (! service('settings')->get('MicrosoftOAuth.enabled')) {
            return '';
        }

        return strtr(lang('Admin.userInviteMicrosoftBody', [], $locale), [
            '{microsoftLink}' => esc(url_to('microsoft/start', $locale)),
        ]);
    }

    public function renderHtml(array $rendered): string
    {
        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'
            . '<html><head><meta name="x-apple-disable-message-reformatting">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">'
            . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            . '<title>' . esc($rendered['subject']) . '</title></head><body>'
            . $rendered['body'] . '</body></html>';
    }

    public function save(string $type, string $locale, string $subject, string $body): void
    {
        service('settings')->setMany([
            self::settingKey($type, $locale, 'subject') => $subject,
            self::settingKey($type, $locale, 'body')    => $body,
        ]);
    }

    public function reset(string $type, string $locale): void
    {
        service('settings')->forgetMany([
            self::settingKey($type, $locale, 'subject'),
            self::settingKey($type, $locale, 'body'),
        ]);
    }

    public static function settingKey(string $type, string $locale, string $field): string
    {
        return 'MailTemplates.' . str_replace('-', '_', $type) . '_' . str_replace('-', '_', $locale) . '_' . $field;
    }

    public function validContent(string $type, string $subject, string $body): bool
    {
        $definition = self::TYPES[$type];
        preg_match_all('/\{([a-zA-Z][a-zA-Z0-9]*)\}/', $subject, $subjectMatches);
        preg_match_all('/\{([a-zA-Z][a-zA-Z0-9]*)\}/', $body, $bodyMatches);

        return ! preg_match('/[\r\n]/', $subject)
            && array_diff($subjectMatches[1], $definition['subjectVariables']) === []
            && array_diff($bodyMatches[1], $definition['variables']) === []
            && str_contains($body, '{' . $definition['required'] . '}');
    }

    private function languageKey(string $type, string $field): string
    {
        if ($type === 'invitation') {
            return 'Admin.userInvite' . $field;
        }

        if ($field === 'Subject') {
            return 'Auth.' . match ($type) {
                'magic-link' => 'magicLinkSubject',
                'activation' => 'emailActivateSubject',
                'email-2fa'  => 'email2FASubject',
            };
        }

        return 'Admin.mailTemplate_' . str_replace('-', '_', $type) . $field;
    }
}
