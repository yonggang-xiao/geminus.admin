<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class UiCellsTest extends CIUnitTestCase
{
    public function testSortHeaderPreservesFiltersAndResetsPaging(): void
    {
        $html = view_cell('Geminus\Admin\Cells\SortHeaderCell', [
            'action'  => '/zh-Hans/admin/items', 'field' => 'title', 'label' => '<Title>',
            'sort'    => 'title', 'direction' => 'ASC',
            'filters' => ['q' => '"><script>alert(1)</script>', 'state' => 'enabled', 'page' => 3, 'sort' => 'old', 'direction' => 'ASC'],
        ]);
        $this->assertStringContainsString('aria-sort="ascending"', $html);
        $this->assertStringContainsString('name="direction" value="DESC"', $html);
        $this->assertStringContainsString('name="state" value="enabled"', $html);
        $this->assertStringNotContainsString('name="page"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;Title&gt;', $html);

        $inactive = view_cell('Geminus\Admin\Cells\SortHeaderCell', ['field' => 'created_at', 'defaultDirection' => 'DESC']);
        $this->assertStringContainsString('aria-sort="none"', $inactive);
        $this->assertStringContainsString('name="direction" value="DESC"', $inactive);
    }

    public function testDateFieldLinksErrorsAndHintsWithoutReadingSession(): void
    {
        $html = view_cell('Geminus\Admin\Cells\DateFieldCell', [
            'inputId' => 'expires', 'name' => 'expires', 'label' => 'Expiry', 'value' => '2026-10-09',
            'error'   => '<Invalid>', 'hint' => 'UTC', 'min' => '2026-10-09', 'max' => '2027-10-09', 'required' => true,
        ]);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="expires-hint expires-error"', $html);
        $this->assertStringContainsString('invalid-feedback d-block', $html);
        $this->assertStringContainsString('&lt;Invalid&gt;', $html);
        $this->assertStringContainsString('data-bs-date-max="2027-10-09"', $html);
        $this->assertMatchesRegularExpression('/<input\b[^>]*\srequired[\s>]/', $html);
        $this->assertLessThan(strpos($html, '<input'), strpos($html, 'input-icon-addon'));
        $clean = view_cell('Geminus\Admin\Cells\DateFieldCell', ['inputId' => 'clean', 'name' => 'clean']);
        $this->assertStringNotContainsString('aria-invalid', $clean);
        $this->assertStringNotContainsString('expires-error', $clean);
        $this->assertDoesNotMatchRegularExpression('/<input\b[^>]*\srequired[\s>]/', $clean);
    }

    public function testFilterBarRendersKeywordEnumAndRanges(): void
    {
        $html = view_cell('Geminus\Admin\Cells\FilterBarCell', [
            'action' => '/en/items', 'clearUrl' => '/en/items', 'submitLabel' => 'Filter', 'clearLabel' => 'Clear',
            'hidden' => ['sort' => 'title'],
            'fields' => [
                ['id' => 'q', 'name' => 'q', 'label' => 'Keyword', 'value' => '<query>', 'maxlength' => 100],
                ['type' => 'select', 'id' => 'state', 'name' => 'state', 'label' => 'State', 'value' => '1', 'options' => ['' => 'All', 1 => '<Enabled>']],
                ['type' => 'date', 'id' => 'from', 'name' => 'from', 'label' => 'From', 'value' => '2026-10-01'],
                ['type' => 'number', 'id' => 'amount', 'name' => 'amount_from', 'label' => 'Amount', 'value' => '-0.5'],
            ],
        ]);
        $this->assertStringContainsString('method="get"', $html);
        $this->assertStringContainsString('value="&lt;query&gt;"', $html);
        $this->assertStringContainsString('value="1" selected', $html);
        $this->assertStringContainsString('&lt;Enabled&gt;', $html);
        $this->assertStringContainsString('data-bs-toggle="datepicker"', $html);
        $this->assertStringContainsString('step="any"', $html);
        $this->assertStringContainsString('name="sort" value="title"', $html);
    }

    public function testEmptyStateSelectsOnlyTheApplicableAction(): void
    {
        $parameters = ['message' => '<Empty>', 'createUrl' => '/en/items/create', 'createLabel' => 'Create', 'clearUrl' => '/en/items', 'clearLabel' => 'Clear'];
        $empty      = view_cell('Geminus\Admin\Cells\EmptyStateCell', $parameters);
        $this->assertStringContainsString('href="/en/items/create"', $empty);
        $this->assertStringContainsString('&lt;Empty&gt;', $empty);
        $filtered = view_cell('Geminus\Admin\Cells\EmptyStateCell', ['filtered' => true] + $parameters);
        $this->assertStringContainsString('href="/en/items"', $filtered);
        $this->assertStringNotContainsString('/en/items/create', $filtered);
        $readonly = view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => 'Empty']);
        $this->assertStringNotContainsString('<a ', $readonly);
    }

    public function testFilterBarRejectsUnsupportedControls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        view_cell('Geminus\Admin\Cells\FilterBarCell', ['fields' => [['type' => 'password']]]);
    }

    public function testPaginationUsesTheSuppliedPagerAndGroup(): void
    {
        $pager = Services::pager(null, null, false);
        $pager->store('items', 2, 20, 45);
        $html = view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links('items'), 'total' => $pager->getTotal('items'), 'currentPage' => $pager->getCurrentPage('items'), 'perPage' => $pager->getPerPage('items'), 'totalLabel' => 'Total']);
        $this->assertStringContainsString('Total: 45', $html);
        $this->assertStringContainsString('(21 - 40)', $html);
        $this->assertStringContainsString('page_items=', $html);
        $pager = Services::pager(null, null, false);
        $pager->store('default', 1, 20, 0);
        $empty = view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links(), 'total' => $pager->getTotal(), 'totalLabel' => 'Total']);
        $this->assertStringContainsString('Total: 0', $empty);
        $this->assertStringNotContainsString('(1 -', $empty);
        $lastPage = view_cell('Geminus\Admin\Cells\PaginationCell', ['total' => 45, 'currentPage' => 3, 'perPage' => 20, 'totalLabel' => 'Total']);
        $this->assertStringContainsString('(41 - 45)', $lastPage);
        $outOfRange = view_cell('Geminus\Admin\Cells\PaginationCell', ['total' => 45, 'currentPage' => 4, 'perPage' => 20, 'totalLabel' => 'Total']);
        $this->assertStringNotContainsString('(61 -', $outOfRange);
    }

    public function testImportReportShowsMappedColumnsSummaryAndEscapedErrors(): void
    {
        $html = view_cell('Geminus\Admin\Cells\ImportReportCell', [
            'title'        => 'Import', 'rowLabel' => 'Row', 'resultLabel' => 'Result', 'reasonLabel' => 'Reason',
            'columns'      => ['title' => 'Title'], 'resultLabels' => ['created' => 'Created', 'error' => 'Failed'],
            'reasonLabels' => ['invalid' => 'Invalid title'],
            'rows'         => [
                ['row' => 2, 'title' => '<Title>', 'result' => 'created', 'reason' => ''],
                ['row' => 4, 'title' => 'Bad', 'result' => 'error', 'reason' => 'invalid', 'errors' => ['title' => '<Required>']],
            ],
        ]);
        $this->assertStringContainsString('Created: 1', $html);
        $this->assertStringContainsString('Failed: 1', $html);
        $this->assertStringContainsString('<td>4</td>', $html);
        $this->assertStringContainsString('Invalid title', $html);
        $this->assertStringContainsString('&lt;Title&gt;', $html);
        $this->assertStringContainsString('&lt;Required&gt;', $html);
        $this->assertStringNotContainsString('Email', $html);
        $empty = view_cell('Geminus\Admin\Cells\ImportReportCell', ['rows' => [], 'resultLabels' => ['created' => 'Created']]);
        $this->assertStringContainsString('Created: 0', $empty);
    }

    public function testAttachmentsKeepPostCsrfAndConfiguredResourceRoutes(): void
    {
        $labels = ['file' => 'File', 'size' => 'Size', 'actions' => 'Actions', 'upload' => 'Upload', 'download' => 'Download', 'remove' => 'Remove', 'empty' => 'Empty'];
        $html   = view_cell('Geminus\Admin\Cells\AttachmentsCell', [
            'uploadUrl'     => '/en/admin/users/12/attachments/upload',
            'downloadRoute' => 'admin/users/attachments/download', 'removeRoute' => 'admin/users/attachments/remove', 'routeArguments' => [12],
            'labels'        => $labels, 'accept' => '.pdf', 'hint' => 'PDF only', 'error' => '<Invalid file>',
            'attachments'   => [['id' => 34, 'original_name' => '<script>.pdf', 'size_bytes' => 2048]],
        ]);
        $this->assertSame(2, substr_count($html, 'method="post"'));
        $this->assertSame(2, substr_count($html, 'name="' . csrf_token() . '"'));
        $this->assertStringContainsString('href="' . route_to('admin/users/attachments/download', 12, 34) . '"', $html);
        $this->assertStringContainsString('action="' . route_to('admin/users/attachments/remove', 12, 34) . '"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('aria-describedby="attachment-file-hint attachment-file-error"', $html);
        $this->assertStringContainsString('&lt;Invalid file&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;.pdf', $html);
        $readonly = view_cell('Geminus\Admin\Cells\AttachmentsCell', ['labels' => $labels, 'attachments' => [['id' => 34, 'original_name' => 'file.pdf', 'size_bytes' => 2048]]]);
        $this->assertStringNotContainsString('<form', $readonly);
        $this->assertStringNotContainsString('<a ', $readonly);
        $empty = view_cell('Geminus\Admin\Cells\AttachmentsCell', ['labels' => $labels]);
        $this->assertStringContainsString('Empty', $empty);
        $this->assertStringNotContainsString('file.pdf', $empty);
    }
}
