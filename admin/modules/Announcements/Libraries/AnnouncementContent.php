<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use CodeIgniter\Validation\ValidationInterface;

class AnnouncementContent
{
    public static function rules(): array
    {
        return [
            'title' => ['label' => 'Announcements.name', 'rules' => 'required|string|max_length[150]|regex_match[/\A[^\x00]*\z/u]'],
            'body'  => ['label' => 'Announcements.body', 'rules' => 'required|string|max_length[2000]|regex_match[/\A[^\x00]*\z/u]'],
        ];
    }

    public static function normalize(array $input): array
    {
        foreach (['title', 'body'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field], " \t\n\r\x0B");
            }
        }

        return $input;
    }

    public static function apiInput(array $input, ValidationInterface $validation): array
    {
        $input = self::normalize($input);
        $validation->reset();
        $validation->setRules(self::rules());
        $valid  = $validation->run($input);
        $errors = $validation->getErrors();

        foreach (array_diff(array_keys($input), ['title', 'body']) as $field) {
            $errors[$field] = 'Unknown field.';
        }
        if (! $valid || $errors !== []) {
            throw new InvalidAnnouncementInput($errors);
        }

        return $validation->getValidated();
    }
}
