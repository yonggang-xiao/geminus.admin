<?php

declare(strict_types=1);

namespace Modules\Announcements\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\Libraries\DataManagement\AttachmentPreview;
use Geminus\Admin\Libraries\DataManagement\Attachments;
use Geminus\Admin\Models\AttachmentModel;
use InvalidArgumentException;
use Modules\Announcements\Models\AnnouncementModel;
use Throwable;

class AnnouncementAttachments extends BaseController
{
    public function index(int $announcementId): ResponseInterface|string
    {
        $announcement = (new AnnouncementModel())->find($announcementId);
        if ($announcement === null) {
            return $this->response->setStatusCode(404);
        }
        $model = new AttachmentModel();
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\Announcements\Views\attachments', [
            'me'          => auth()->user(), 'page_title' => lang('Admin.attachments'), 'announcement' => $announcement,
            'attachments' => $model->forResource('announcement', $announcementId)->orderBy('id', 'DESC')->paginate(20),
            'pager'       => $model->pager,
            'accept'      => implode(',', array_map(static fn (string $extension): string => '.' . $extension, array_keys(Attachments::FILE_TYPES))),
            'maxSize'     => (string) (Attachments::maxBytes() / (1024 * 1024)),
        ]);
    }

    public function upload(int $announcementId): RedirectResponse|ResponseInterface
    {
        if ((new AnnouncementModel())->find($announcementId) === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            (new Attachments())->upload('announcement', $announcementId, $this->request->getFile('file'), (int) auth()->id());
        } catch (InvalidArgumentException $exception) {
            return redirect()->to(route_to('admin/announcements/attachments', $announcementId))->with('attachment_errors', ['file' => lang('Admin.attachmentInvalid', [(string) (Attachments::maxBytes() / (1024 * 1024))])]);
        } catch (Throwable $exception) {
            log_message('error', 'Announcement attachment upload failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/announcements/attachments', $announcementId))->with('alert', ['type' => 'danger', 'message' => lang('Admin.attachmentFailed')]);
        }

        return redirect()->to(route_to('admin/announcements/attachments', $announcementId))->with('alert', ['type' => 'success', 'message' => lang('Admin.attachmentSaved')]);
    }

    public function download(int $announcementId, int $attachmentId): ResponseInterface
    {
        return $this->fileResponse($announcementId, $attachmentId, false);
    }

    public function preview(int $announcementId, int $attachmentId): ResponseInterface
    {
        return $this->fileResponse($announcementId, $attachmentId, true);
    }

    private function fileResponse(int $announcementId, int $attachmentId, bool $preview): ResponseInterface
    {
        if ((new AnnouncementModel())->visibleTo(auth()->user()->can('announcements.manage'))->find($announcementId) === null) {
            return $this->response->setStatusCode(404);
        }
        $service    = new Attachments();
        $attachment = $service->find('announcement', $announcementId, $attachmentId);
        $path       = $attachment === null ? null : $service->path($attachment);
        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        $mime = AttachmentPreview::mimeType($attachment['mime_type']);
        if ($preview && $mime === null) {
            return $this->response->setStatusCode(415);
        }

        $response = $this->response->download($path, null)->setFileName($attachment['original_name'])
            ->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Content-Type-Options', 'nosniff');

        if ($preview) {
            $response->inline()->setContentType($mime, $mime === 'text/plain' ? 'UTF-8' : '')
                ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'")
                ->setHeader('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    public function remove(int $announcementId, int $attachmentId): RedirectResponse|ResponseInterface
    {
        if ((new AnnouncementModel())->find($announcementId) === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            if (! (new Attachments())->remove('announcement', $announcementId, $attachmentId)) {
                return $this->response->setStatusCode(404);
            }
        } catch (Throwable $exception) {
            log_message('error', 'Announcement attachment removal failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/announcements/attachments', $announcementId))->with('alert', ['type' => 'danger', 'message' => lang('Admin.attachmentFailed')]);
        }

        return redirect()->to(route_to('admin/announcements/attachments', $announcementId))->with('alert', ['type' => 'success', 'message' => lang('Admin.attachmentRemoved')]);
    }
}
