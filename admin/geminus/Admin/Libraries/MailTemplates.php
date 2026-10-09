<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Language\Language;
use CodeIgniter\Settings\Settings;
use InvalidArgumentException;

class MailTemplates
{
    public const TYPES = [
        'invitation' => ['label' => 'mailInvitation', 'html' => true, 'required' => 'link', 'variables' => ['username', 'link', 'microsoftLogin'], 'subjectVariables' => ['username']],
        'magic-link' => ['label' => 'mailMagicLink', 'html' => true, 'required' => 'link', 'variables' => ['username', 'link', 'ipAddress', 'userAgent', 'date'], 'subjectVariables' => ['username', 'date']],
        'activation' => ['label' => 'mailActivation', 'html' => true, 'required' => 'code', 'variables' => ['username', 'code', 'ipAddress', 'userAgent', 'date'], 'subjectVariables' => ['username', 'date']],
        'email-2fa'  => ['label' => 'mailEmail2fa', 'html' => true, 'required' => 'code', 'variables' => ['username', 'code', 'ipAddress', 'userAgent', 'date'], 'subjectVariables' => ['username', 'date']],
    ];

    public function __construct(private readonly Settings $settings, private readonly Language $language, private readonly array $supportedLocales)
    {
    }

    public function get(string $type, string $locale): array
    {
        if (! isset(self::TYPES[$type]) || ! in_array($locale, $this->supportedLocales, true)) {
            throw new InvalidArgumentException('Unknown mail template or locale.');
        }

        $language = clone $this->language;
        $language->setLocale($locale);

        return [
            'subject' => $this->settings->get(self::settingKey($type, $locale, 'subject')) ?? $language->getLine($this->languageKey($type, 'Subject')),
            'body'    => $this->settings->get(self::settingKey($type, $locale, 'body')) ?? str_replace(['{0}', '{1}'], ['{username}', '{link}'], $language->getLine($this->languageKey($type, 'Body'))),
        ];
    }

    public function render(string $type, string $locale, array $values, string $microsoftLoginHtml = ''): array
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
            $bodyReplacements['{microsoftLogin}'] = $microsoftLoginHtml;
        }

        return [
            'subject' => strtr($template['subject'], $subjectReplacements),
            'body'    => strtr($template['body'], $bodyReplacements),
        ];
    }

    public function save(string $type, string $locale, string $subject, string $body): void
    {
        $this->settings->setMany([
            self::settingKey($type, $locale, 'subject') => $subject,
            self::settingKey($type, $locale, 'body')    => $body,
        ]);
    }

    public function reset(string $type, string $locale): void
    {
        $this->settings->forgetMany([
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
