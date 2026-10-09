<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use Geminus\Admin\Cells\TimezoneSelectorCell;
use Geminus\Admin\Libraries\AvatarFiles;
use Geminus\Admin\Libraries\DataManagement\UploadStorage;
use Geminus\Admin\Libraries\MicrosoftLinks;
use RuntimeException;
use Throwable;

class Profile extends BaseController
{
    public function __construct(private readonly ?UploadStorage $avatarStorage = null)
    {
    }

    public function index(): string
    {
        $user = auth()->user();

        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Geminus\Admin\Views\profile', [
            'me'                    => $user,
            'page_title'            => lang('Admin.accountSettings'),
            'localAccount'          => ! empty($user->getEmailIdentity()?->secret2),
            'tokens'                => $user->accessTokens(),
            'languages'             => config('App')->supportedLocales,
            'minimumPasswordLength' => config('Auth')->minimumPasswordLength,
            'microsoftLinked'       => $user->getIdentity(MicrosoftLinks::IDENTITY_TYPE) !== null,
            'microsoftEnabled'      => service('settings')->get('MicrosoftOAuth.enabled'),
        ]);
    }

    public function update(): RedirectResponse
    {
        $user          = auth()->user();
        $usernameRules = config('Auth')->usernameValidationRules;
        if ($this->request->getPost('username') === $user->username) {
            $usernameRules['rules'] = ['required'];
        }

        $validation = service('validation');
        $validation->setRules([
            'username' => $usernameRules,
            'language' => ['label' => 'Admin.language', 'rules' => 'required|max_length[20]'],
            'timezone' => ['label' => 'Admin.timezone', 'rules' => 'required|max_length[64]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('profile_errors', $validation->getErrors());
        }

        $data = $validation->getValidated();
        if (! in_array($data['language'], config('App')->supportedLocales, true)) {
            return redirect()->back()->withInput()->with('profile_errors', ['language' => lang('Admin.invalidPreference')]);
        }

        if (! in_array($data['timezone'], TimezoneSelectorCell::TIMEZONES, true)) {
            return redirect()->back()->withInput()->with('profile_errors', ['timezone' => lang('Admin.invalidPreference')]);
        }

        if ($data['username'] !== $user->username && service('userProvisioning')->usernameTaken($data['username'], $user->id)) {
            return redirect()->back()->withInput()->with('profile_errors', ['username' => lang('Admin.usernameTaken')]);
        }

        $user->fill($data);
        auth()->getProvider()->save($user);

        return redirect()->to(site_url($data['language'] . '/admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.profileSaved')]);
    }

    public function language(): RedirectResponse
    {
        $locale = $this->request->getPost('language');
        if (! is_string($locale) || ! in_array($locale, config('App')->supportedLocales, true)) {
            return redirect()->back()->with('alert', ['type' => 'danger', 'message' => lang('Admin.invalidPreference')]);
        }

        $user           = auth()->user();
        $user->language = $locale;
        auth()->getProvider()->save($user);

        $returnPath = $this->request->getPost('return');
        $segments   = is_string($returnPath) ? explode('/', trim($returnPath, '/')) : [];
        if (count($segments) < 2 || ! in_array($segments[0], config('App')->supportedLocales, true) || $segments[1] !== 'admin') {
            return redirect()->to(site_url($locale . '/admin/dashboard'));
        }

        array_shift($segments);

        foreach ($segments as $segment) {
            if (! preg_match('/\A[a-zA-Z0-9_-]+\z/D', $segment)) {
                return redirect()->to(site_url($locale . '/admin/dashboard'));
            }
        }

        return redirect()->to(site_url($locale . '/' . implode('/', $segments)));
    }

    public function avatar(): RedirectResponse
    {
        $validation = service('validation');
        $validation->setRules([
            'avatar' => ['label' => 'Admin.avatar', 'rules' => AvatarFiles::rules()],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/profile'))->with('avatar_errors', $validation->getErrors());
        }

        $storage  = $this->avatarStorage ?? AvatarFiles::storage();
        $user     = auth()->user();
        $previous = $user->avatar;
        $filename = null;

        try {
            $filename     = $storage->store($this->request->getFile('avatar'));
            $user->avatar = $filename;
            if (! auth()->getProvider()->save($user)) {
                throw new RuntimeException('Could not save avatar.');
            }
        } catch (Throwable $exception) {
            $user->avatar = $previous;
            $this->deleteAvatar($filename);
            log_message('error', 'Avatar upload failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.avatarFailed')]);
        }
        $this->deleteAvatar($previous);

        return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.avatarSaved')]);
    }

    public function removeAvatar(): RedirectResponse
    {
        $user         = auth()->user();
        $previous     = $user->avatar;
        $user->avatar = null;

        try {
            if (! auth()->getProvider()->save($user)) {
                throw new RuntimeException('Could not remove avatar.');
            }
        } catch (Throwable $exception) {
            $user->avatar = $previous;
            log_message('error', 'Avatar removal failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.avatarFailed')]);
        }
        $this->deleteAvatar($previous);

        return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.avatarRemoved')]);
    }

    private function deleteAvatar(?string $filename): void
    {
        try {
            ($this->avatarStorage ?? AvatarFiles::storage())->delete($filename);
        } catch (Throwable $exception) {
            log_message('error', 'Avatar file cleanup failed: {type}', ['type' => $exception::class]);
        }
    }

    public function password(): RedirectResponse
    {
        $user = auth()->user();
        if (empty($user->getEmailIdentity()?->secret2)) {
            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.localOnly')]);
        }

        $validation = service('validation');
        $validation->setRules([
            'current_password' => ['label' => 'Admin.currentPassword', 'rules' => 'required'],
            'new_password'     => ['label' => 'Admin.newPassword', 'rules' => 'required|max_length[255]'],
            'confirm_password' => ['label' => 'Admin.confirmPassword', 'rules' => 'required|matches[new_password]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/profile'))->with('password_errors', $validation->getErrors());
        }

        $data      = $validation->getValidated();
        $passwords = service('passwords');
        if (! $passwords->verify($data['current_password'], $user->getPasswordHash())) {
            return redirect()->to(route_to('admin/profile'))->with('password_errors', ['current_password' => lang('Admin.incorrectPassword')]);
        }

        $result = $passwords->check($data['new_password'], $user);
        if (! $result->isOK()) {
            return redirect()->to(route_to('admin/profile'))->with('password_errors', ['new_password' => $result->reason()]);
        }

        $user->setPassword($data['new_password']);
        auth()->getProvider()->save($user);

        return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.passwordSaved')]);
    }

    public function createToken(): RedirectResponse
    {
        $validation = service('validation');
        $validation->setRules([
            'name'    => ['label' => 'Admin.tokenName', 'rules' => 'required|max_length[100]'],
            'expires' => ['label' => 'Admin.tokenExpires', 'rules' => 'required|valid_date[Y-m-d]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/profile'))->withInput()->with('token_errors', $validation->getErrors());
        }

        $data    = $validation->getValidated();
        $expires = Time::parse($data['expires'] . ' 23:59:59', 'UTC');
        if ($expires->isBefore(Time::now('UTC'))) {
            return redirect()->to(route_to('admin/profile'))->withInput()->with('token_errors', ['expires' => lang('Admin.futureExpiry')]);
        }

        $token = auth()->user()->generateAccessToken($data['name'], ['*'], $expires);

        return redirect()->to(route_to('admin/profile'))->with('alert', [
            'type'    => 'warning',
            'message' => lang('Admin.tokenOnce'),
            'detail'  => $token->raw_token,
        ]);
    }

    public function revokeToken(int $id): RedirectResponse
    {
        $user  = auth()->user();
        $token = $user->getAccessTokenById($id);
        if ($token === null) {
            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.tokenNotFound')]);
        }

        $user->revokeAccessTokenBySecret($token->secret);

        return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.tokenRevoked')]);
    }
}
