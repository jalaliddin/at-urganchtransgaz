<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
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
        $writer->addRow(Row::fromValuesWithStyle($columns, (new Style)->withFontBold(true)));
        $count = 0;
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
            $count++;
        }
        $writer->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return ['contents' => $contents, 'rows' => $count];
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
