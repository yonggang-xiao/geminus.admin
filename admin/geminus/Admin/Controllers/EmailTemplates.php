<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\Libraries\MailTemplates;

class EmailTemplates extends BaseController
{
    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->can('email-templates.manage')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');
        $type   = (string) $this->request->getGet('type');
        $locale = (string) $this->request->getGet('locale');
        $type   = isset(MailTemplates::TYPES[$type]) ? $type : 'invitation';
        $locale = in_array($locale, config('App')->supportedLocales, true) ? $locale : $this->request->getLocale();

        return view('Geminus\Admin\Views\email_templates', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.mailTemplates'),
            'template'   => (new MailTemplates())->get($type, $locale),
            'type'       => $type,
            'locale'     => $locale,
            'types'      => MailTemplates::TYPES,
        ]);
    }

    public function update(string $type, string $locale): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('email-templates.manage')) {
            return $this->response->setStatusCode(403);
        }

        if (! isset(MailTemplates::TYPES[$type]) || ! in_array($locale, config('App')->supportedLocales, true)) {
            return $this->response->setStatusCode(404);
        }

        $validation = service('validation');
        $validation->setRules([
            'subject' => 'required|max_length[255]',
            'body'    => 'required|max_length[10000]',
        ]);
        $url = route_to('admin/mail/templates') . '?type=' . $type . '&locale=' . $locale;

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to($url)->withInput()->with('template_errors', $validation->getErrors());
        }

        $data = $validation->getValidated();
        if (! (new MailTemplates())->validContent($type, $data['subject'], $data['body'])) {
            return redirect()->to($url)->withInput()->with('template_errors', ['body' => lang('Admin.mailTemplateInvalid')]);
        }

        (new MailTemplates())->save($type, $locale, $data['subject'], $data['body']);

        return redirect()->to($url)->with('alert', ['type' => 'success', 'message' => lang('Admin.mailTemplateSaved')]);
    }

    public function reset(string $type, string $locale): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('email-templates.manage')) {
            return $this->response->setStatusCode(403);
        }

        if (! isset(MailTemplates::TYPES[$type]) || ! in_array($locale, config('App')->supportedLocales, true)) {
            return $this->response->setStatusCode(404);
        }

        (new MailTemplates())->reset($type, $locale);

        return redirect()->to(route_to('admin/mail/templates') . '?type=' . $type . '&locale=' . $locale)
            ->with('alert', ['type' => 'success', 'message' => lang('Admin.mailTemplateReset')]);
    }
}
