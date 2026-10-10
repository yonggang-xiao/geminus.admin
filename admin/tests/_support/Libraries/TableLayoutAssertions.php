<?php

namespace Tests\Support\Libraries;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Assert;

final class TableLayoutAssertions
{
    public static function assertTablesInCards(string $html, int $expectedTables = 1, bool $paginated = false): void
    {
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath  = new DOMXPath($document);
        $tables = $xpath->query('//table');
        Assert::assertCount($expectedTables, $tables, 'The expected tables must be rendered.');

        foreach ($tables as $table) {
            Assert::assertCount(1, $xpath->query('self::table[contains(concat(" ", normalize-space(@class), " "), " table ") and contains(concat(" ", normalize-space(@class), " "), " card-table ")]', $table), 'Tables must use the card-table layout.');
            $containers = $xpath->query('parent::*[contains(concat(" ", normalize-space(@class), " "), " table-responsive ")]', $table);
            Assert::assertCount(1, $containers, 'Tables must be direct children of a responsive container.');
            $cards = $xpath->query('parent::*[contains(concat(" ", normalize-space(@class), " "), " card ")]', $containers->item(0));
            Assert::assertCount(1, $cards, 'Responsive containers must be direct children of the card, not its body.');

            if ($paginated) {
                $footers = $xpath->query('following-sibling::*[contains(concat(" ", normalize-space(@class), " "), " card-footer ")]', $containers->item(0));
                Assert::assertCount(1, $footers, 'Pagination must follow the table inside the same card.');
                Assert::assertCount(1, $xpath->query('.//ul[contains(concat(" ", normalize-space(@class), " "), " pagination ")]', $footers->item(0)), 'The card footer must contain pagination, including for empty lists.');
            }
        }

        if ($paginated) {
            Assert::assertCount($expectedTables, $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " pagination ")]'), 'Pagination must not be duplicated outside the table cards.');
        }
    }
}
