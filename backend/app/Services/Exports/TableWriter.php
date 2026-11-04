<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/** Renders a title, header and rows to XLSX (openspout, streamed) or PDF (dompdf). */
class TableWriter
{
    /** dompdf renders everything in memory; larger tables must use Excel. */
    public const PDF_MAX_ROWS = 2000;

    /**
     * @param  list<string>  $columns
     * @param  iterable<list<string|int|float|null>>  $rows
     * @return array{contents: string, rows: int}
     */
    public function xlsx(string $title, array $columns, iterable $rows): array
    {
        $path = tempnam(sys_get_temp_dir(), 'export');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($title, 0, 31));
        $bold = (new Style)->withFontBold(true);
        $writer->addRow(new Row(array_map(fn (string $c): Cell => new StringCell($c, $bold), $columns)));
        $count = 0;
        foreach ($rows as $row) {
            $writer->addRow(self::row($row));
            $count++;
        }
        $writer->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return ['contents' => $contents, 'rows' => $count];
    }

    /**
     * Strings always become text cells: Cell::fromValue() would turn user text
     * starting with "=" into a live formula (spreadsheet formula injection).
     *
     * @param  list<string|int|float|null>  $values
     */
    private static function row(array $values): Row
    {
        return new Row(array_map(
            fn (string|int|float|null $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value),
            $values,
        ));
    }

    /**
     * @param  list<string>  $columns
     * @param  iterable<list<string|int|float|null>>  $rows
     * @return array{contents: string, rows: int}
     */
    public function pdf(string $title, array $columns, iterable $rows, string $locale): array
    {
        $data = [];
        foreach ($rows as $row) {
            $data[] = $row;
            if (count($data) > self::PDF_MAX_ROWS) {
                throw new ExportTooLargeException(__('exports.pdf_too_large', ['max' => self::PDF_MAX_ROWS], $locale));
            }
        }

        $contents = Pdf::loadView('exports.table', [
            'title' => $title,
            'columns' => $columns,
            'rows' => $data,
            'generatedAt' => now((string) config('app.display_timezone'))->format('d.m.Y H:i'),
            'organization' => (string) config('app.name'),
        ])->setPaper('a4', 'landscape')->output();

        return ['contents' => (string) $contents, 'rows' => count($data)];
    }
}
