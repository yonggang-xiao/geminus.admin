<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Geminus\Admin\Libraries\QueuedEmail;
use RuntimeException;

class EmailSettings extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Geminus\Admin\Views\settings_email', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.systemSettings'),
            'email'      => service('settings')->getMany([
                'Email.fromEmail', 'Email.fromName', 'Email.protocol', 'Email.SMTPHost',
                'Email.SMTPUser', 'Email.SMTPPort', 'Email.SMTPCrypto',
            ]),
        ]);
    }

    public function update(): RedirectResponse|ResponseInterface
    {
        $validation = service('validation');
        $rules      = [
            'fromEmail' => ['label' => 'Admin.senderEmail', 'rules' => 'required|valid_email|max_length[254]'],
            'fromName'  => ['label' => 'Admin.senderName', 'rules' => 'required|max_length[100]'],
            'protocol'  => ['label' => 'Admin.mailProtocol', 'rules' => 'required|in_list[mail,smtp]'],
        ];
        if ($this->request->getPost('protocol') === 'smtp') {
            $rules += [
                'SMTPHost'   => ['label' => 'Admin.smtpHost', 'rules' => 'required|max_length[255]'],
                'SMTPUser'   => ['label' => 'Admin.smtpUser', 'rules' => 'permit_empty|max_length[255]'],
                'SMTPPort'   => ['label' => 'Admin.smtpPort', 'rules' => 'required|integer|greater_than[0]|less_than[65536]'],
                'SMTPCrypto' => ['label' => 'Admin.smtpCrypto', 'rules' => 'permit_empty|in_list[tls,ssl]'],
            ];
        }
        $validation->setRules($rules);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('email_errors', $validation->getErrors());
        }

        $data     = $validation->getValidated();
        $settings = [
            'Email.fromEmail' => $data['fromEmail'],
            'Email.fromName'  => $data['fromName'],
            'Email.protocol'  => $data['protocol'],
        ];
        if ($data['protocol'] === 'smtp') {
            $settings += [
                'Email.SMTPHost'   => $data['SMTPHost'],
                'Email.SMTPUser'   => $data['SMTPUser'] ?? '',
                'Email.SMTPPort'   => (int) $data['SMTPPort'],
                'Email.SMTPCrypto' => $data['SMTPCrypto'] ?? '',
            ];
        }

        $db = db_connect(config('Settings')->database['group']);
        $db->transStart();

        try {
            foreach ($settings as $key => $value) {
                service('settings')->set($key, $value);
            }
        } finally {
            $db->transComplete();
        }
        if ($db->transStatus() === false) {
            throw new RuntimeException('Failed to persist email settings.');
        }
        Services::resetSingle('email');

        return redirect()->to(route_to('admin/settings/email'))->with('alert', ['type' => 'success', 'message' => lang('Admin.emailSettingsSaved')]);
    }

    public function sendTest(): RedirectResponse|ResponseInterface
    {
        $validation = service('validation');
        $validation->setRules([
            'test_email' => ['label' => 'Admin.testRecipient', 'rules' => 'required|valid_email|max_length[254]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/settings/email'))->withInput()->with('test_email_errors', $validation->getErrors());
        }

        if (! service('settings')->get('Email.fromEmail')) {
            return redirect()->to(route_to('admin/settings/email'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.testEmailNotConfigured')]);
        }

        $email = service('email');
        $email->setTo($validation->getValidated()['test_email']);
        $email->setSubject(lang('Admin.testEmailSubject'));
        $email->setMessage(lang('Admin.testEmailBody'));
        $sent = $email instanceof QueuedEmail ? $email->sendDirect() : $email->send();

        $alert = [
            'type'    => $sent ? 'success' : 'danger',
            'message' => lang($sent ? 'Admin.testEmailSent' : 'Admin.testEmailFailed'),
        ];

        if (! $sent) {
            $details = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['</pre>', '<br>', '<br/>', '<br />'], ' ', $email->printDebugger([])))));

            foreach ([$email->SMTPPass, $email->SMTPUser] as $secret) {
                if ($secret !== '') {
                    $details = str_replace($secret, '[redacted]', $details);
                }
            }
            if ($details !== '') {
                $alert['detail'] = mb_substr($details, -2000);
            }
        }

        return redirect()->to(route_to('admin/settings/email'))->with('alert', $alert);
    }
}
