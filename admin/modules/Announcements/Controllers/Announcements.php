<?php

declare(strict_types=1);

namespace Modules\Announcements\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\Libraries\DataManagement\Csv;
use Geminus\Admin\Libraries\DataManagement\CsvImport;
use Geminus\Admin\Libraries\DataManagement\ListQuery;
use Geminus\Admin\Models\AttachmentModel;
use InvalidArgumentException;
use Modules\Announcements\Models\AnnouncementModel;
use RuntimeException;

class Announcements extends BaseController
{
    private const CSV_COLUMNS  = ['title', 'body'];
    private const EXPORT_LIMIT = 10000;

    public function index(): string
    {
        $query = $this->listQuery();
        $model = $this->filteredModel($query);
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\Announcements\Views\index', [
            'me'            => auth()->user(),
            'page_title'    => lang('Announcements.title'),
            'announcements' => $model->paginate($query->perPage),
            'pager'         => $model->pager,
            'query'         => $query,
            'createdRange'  => $query->range('created', 'date'),
            'filters'       => $this->listFilters($query),
            'filtered'      => $query->search !== '' || $query->range('created', 'date') !== ['from' => '', 'to' => ''] || $this->statusFilter() !== '',
            'canManage'     => auth()->user()->can('announcements.manage'),
            'status'        => $this->statusFilter(),
        ]);
    }

    public function create(): string
    {
        return view('Modules\Announcements\Views\create', [
            'me'           => auth()->user(),
            'page_title'   => lang('Announcements.create'),
            'announcement' => [],
            'formAction'   => route_to('admin/announcements/store'),
        ]);
    }

    public function store(): RedirectResponse
    {
        $validation = service('validation');
        $validation->setRules($this->rules());

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/announcements/create'))->withInput()->with('errors', $validation->getErrors());
        }

        $announcementId = (new AnnouncementModel())->insert($validation->getValidated() + ['status' => 'draft', 'published_at' => null]);
        if ($announcementId === false) {
            return redirect()->to(route_to('admin/announcements/create'))->withInput()->with('alert', ['type' => 'danger', 'message' => lang('Announcements.saveFailed')]);
        }

        return redirect()->to(route_to('admin/announcements/show', $announcementId))->with('alert', [
            'type' => 'success', 'message' => lang('Announcements.saved'),
        ]);
    }

    public function show(int $announcementId): ResponseInterface|string
    {
        $canManage    = auth()->user()->can('announcements.manage');
        $announcement = (new AnnouncementModel())->visibleTo($canManage)->find($announcementId);
        if ($announcement === null) {
            return $this->response->setStatusCode(404);
        }
        $attachments = new AttachmentModel();
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\Announcements\Views\show', [
            'me'           => auth()->user(), 'page_title' => $announcement['title'],
            'announcement' => $announcement, 'canManage' => $canManage,
            'attachments'  => $attachments->forResource('announcement', $announcementId)->orderBy('id', 'DESC')->paginate(20),
            'pager'        => $attachments->pager,
        ]);
    }

    public function edit(int $announcementId): ResponseInterface|string
    {
        $announcement = (new AnnouncementModel())->find($announcementId);
        if ($announcement === null) {
            return $this->response->setStatusCode(404);
        }
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\Announcements\Views\create', [
            'me'           => auth()->user(), 'page_title' => lang('Announcements.edit'),
            'announcement' => $announcement, 'formAction' => route_to('admin/announcements/update', $announcementId),
        ]);
    }

    public function update(int $announcementId): RedirectResponse|ResponseInterface
    {
        $model = new AnnouncementModel();
        if ($model->find($announcementId) === null) {
            return $this->response->setStatusCode(404);
        }
        $validation = service('validation');
        $validation->setRules($this->rules());
        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/announcements/edit', $announcementId))->withInput()->with('errors', $validation->getErrors());
        }
        if (! $model->update($announcementId, $validation->getValidated())) {
            return redirect()->to(route_to('admin/announcements/edit', $announcementId))->withInput()->with('alert', ['type' => 'danger', 'message' => lang('Announcements.saveFailed')]);
        }

        return redirect()->to(route_to('admin/announcements/show', $announcementId))->with('alert', ['type' => 'success', 'message' => lang('Announcements.saved')]);
    }

    public function publish(int $announcementId): RedirectResponse|ResponseInterface
    {
        $model        = new AnnouncementModel();
        $announcement = $model->find($announcementId);
        if ($announcement === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            service('announcementpublication')->publish($announcementId, (int) auth()->user()->id);
        } catch (RuntimeException $exception) {
            return redirect()->to(route_to('admin/announcements/show', $announcementId))->with('alert', ['type' => 'danger', 'message' => lang('Announcements.saveFailed')]);
        }

        return redirect()->to(route_to('admin/announcements/show', $announcementId))->with('alert', ['type' => 'success', 'message' => lang('Announcements.published')]);
    }

    private function listQuery(): ListQuery
    {
        $canManage  = auth()->user()->can('announcements.manage');
        $sortFields = [
            'id'           => 'id',
            'title'        => 'title',
            'published_at' => 'published_at',
        ];
        if ($canManage) {
            $sortFields['created_at'] = 'created_at';
        }

        return new ListQuery($this->request->getGet(), $sortFields, $canManage ? 'id' : 'published_at', 15);
    }

    private function filteredModel(ListQuery $query): AnnouncementModel
    {
        $canManage = auth()->user()->can('announcements.manage');
        $model     = (new AnnouncementModel())->visibleTo($canManage);
        $query->apply($model, ['title', 'body'], $canManage ? ['status' => ['field' => 'status', 'values' => ['draft', 'published']]] : [], ranges: [
            'created' => ['field' => $canManage ? 'created_at' : 'published_at', 'type' => 'date'],
        ]);

        return $model;
    }

    public function importForm(): string
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Modules\Announcements\Views\import', [
            'me'         => auth()->user(),
            'page_title' => lang('Announcements.import'),
            'report'     => session('announcement_import_report') ?? [],
        ]);
    }

    public function import(): RedirectResponse
    {
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() > 1024 * 1024 || strtolower($file->getClientExtension()) !== 'csv' || ! in_array($file->getMimeType(), ['text/plain', 'text/csv', 'application/vnd.ms-excel'], true)) {
            return $this->invalidCsv();
        }
        $stream = fopen($file->getTempName(), 'rb');
        if ($stream === false) {
            return $this->invalidCsv();
        }

        try {
            $report = (new CsvImport())->import($stream, self::CSV_COLUMNS, static function (array $data): array {
                if ((new AnnouncementModel())->insert($data + ['status' => 'draft', 'published_at' => null]) === false) {
                    throw new RuntimeException('Could not save announcement.');
                }

                return ['result' => 'created', 'reason' => 'created'];
            }, rules: $this->rules());
        } catch (InvalidArgumentException $exception) {
            return $this->invalidCsv();
        } finally {
            fclose($stream);
        }

        return redirect()->to(route_to('admin/announcements/import'))
            ->with('announcement_import_report', array_map(static fn (array $row): array => [
                'row'    => $row['row'], 'title' => $row['data']['title'],
                'result' => $row['result'], 'reason' => $row['reason'], 'errors' => $row['errors'],
            ], $report))
            ->with('alert', ['type' => 'success', 'message' => lang('Announcements.importFinished')]);
    }

    public function template(): ResponseInterface
    {
        return $this->csvResponse('announcements-template.csv', (new Csv())->write(self::CSV_COLUMNS));
    }

    public function export(): ResponseInterface
    {
        $rows = $this->filteredModel($this->listQuery())->findAll(self::EXPORT_LIMIT + 1);
        if (count($rows) > self::EXPORT_LIMIT) {
            return $this->response->setStatusCode(413)->setBody(lang('Admin.exportLimit'));
        }

        return $this->csvResponse('announcements.csv', (new Csv())->write(self::CSV_COLUMNS, array_map(static fn (array $row): array => [$row['title'], $row['body']], $rows), self::EXPORT_LIMIT));
    }

    private function csvResponse(string $filename, string $csv): ResponseInterface
    {
        return $this->response->setContentType('text/csv')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Content-Type-Options', 'nosniff')->setBody($csv);
    }

    private function invalidCsv(): RedirectResponse
    {
        return redirect()->to(route_to('admin/announcements/import'))->with('errors', ['file' => lang('Announcements.invalidCsv')]);
    }

    private function rules(): array
    {
        return [
            'title' => ['label' => 'Announcements.name', 'rules' => 'required|max_length[150]'],
            'body'  => ['label' => 'Announcements.body', 'rules' => 'required|max_length[2000]'],
        ];
    }

    private function listFilters(ListQuery $query): array
    {
        $range   = $query->range('created', 'date');
        $filters = ['q' => $query->search, 'created_from' => $range['from'], 'created_to' => $range['to']];
        if (auth()->user()->can('announcements.manage')) {
            $filters['status'] = $this->statusFilter();
        }

        return $filters;
    }

    private function statusFilter(): string
    {
        $status = $this->request->getGet('status');

        return auth()->user()->can('announcements.manage') && is_string($status) && in_array($status, ['draft', 'published'], true) ? $status : '';
    }
}
