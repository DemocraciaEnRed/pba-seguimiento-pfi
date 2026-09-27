<?php

namespace App\Services\Csv;

use App\Exports\CsvExport;
use League\Csv\Bom;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams exports as RFC 4180 CSV (comma, CRLF, UTF-8 with BOM so spreadsheets detect the encoding).
 */
class CsvDownload
{
    /**
     * Numbers, signed percentages and the "-" placeholder cannot carry a formula, so they are kept verbatim.
     */
    private const string LITERAL_VALUE = '/^(?:[+-]?\d+(?:[.,]\d+)?%?|-)$/';

    public function download(CsvExport $export, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->write($export),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function write(CsvExport $export): void
    {
        $stream = fopen('php://output', 'w');
        fwrite($stream, Bom::Utf8->value);

        $formulaEscaper = new EscapeFormula();

        $writer = Writer::from($stream)
            ->setEscape('')
            ->setEndOfLine("\r\n")
            ->addFormatter(fn (array $record): array => array_map(
                fn (mixed $value): mixed => is_string($value) && preg_match(self::LITERAL_VALUE, $value) === 1
                    ? $value
                    : $formulaEscaper->escapeRecord([$value])[0],
                $record,
            ));

        $writer->insertOne($export->headings());

        foreach ($export->collection() as $row) {
            $mapped = $export->map($row);
            $writer->insertAll(isset($mapped[0]) && is_array($mapped[0]) ? $mapped : [$mapped]);
        }
    }
}
