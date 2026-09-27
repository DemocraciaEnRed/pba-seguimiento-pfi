<?php

namespace App\Services\Csv;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Csv\Bom;
use League\Csv\Info;
use League\Csv\Reader;

/**
 * Reads spreadsheets saved as CSV by Excel, LibreOffice or Sheets, whatever their encoding and delimiter.
 */
class CsvReader
{
    private const int HEADER_SEARCH_ROWS = 10;

    private const array DELIMITERS = [',', ';', "\t"];

    private const array FORMULA_CHARACTERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Returns the rows below the header row, keyed by normalized header, with their spreadsheet row number.
     *
     * @param  list<string>  $requiredHeaders
     * @return list<array{line: int, values: array<string, string>}>
     *
     * @throws ValidationException
     */
    public function read(string $path, array $requiredHeaders, string $field = 'file'): array
    {
        $reader = Reader::fromString($this->utf8Contents($path))->setEscape('');
        $stats = Info::getDelimiterStats($reader, self::DELIMITERS, self::HEADER_SEARCH_ROWS);
        $reader->setDelimiter((string) array_search(max($stats), $stats, true));

        $required = array_map(self::normalize(...), $requiredHeaders);
        $headers = null;
        $rows = [];

        foreach ($reader->getRecords() as $offset => $record) {
            if ($headers === null) {
                if ($offset >= self::HEADER_SEARCH_ROWS) {
                    break;
                }

                $normalized = array_map(fn (mixed $cell): string => self::normalize((string) $cell), $record);
                $headers = array_diff($required, $normalized) === [] ? $normalized : null;

                continue;
            }

            $values = [];
            foreach ($headers as $index => $header) {
                if ($header !== '' && ! array_key_exists($header, $values)) {
                    $values[$header] = $this->unescapeFormula(trim((string) ($record[$index] ?? '')));
                }
            }

            if (implode('', $values) !== '') {
                $rows[] = ['line' => $offset + 1, 'values' => $values];
            }
        }

        if ($headers === null) {
            throw ValidationException::withMessages([
                $field => 'No encontramos la fila de encabezados en las primeras '.self::HEADER_SEARCH_ROWS.' filas. Tiene que incluir las columnas: '.implode(', ', $requiredHeaders).'.',
            ]);
        }

        return $rows;
    }

    public static function normalize(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim();
    }

    private function utf8Contents(string $path): string
    {
        $contents = (string) file_get_contents($path);
        $bom = Bom::tryFromSequence($contents);

        if ($bom !== null) {
            $contents = substr($contents, $bom->length());

            return $bom->isUtf8() ? $contents : mb_convert_encoding($contents, 'UTF-8', $bom->encoding());
        }

        // Excel's plain "CSV" option saves in the Windows ANSI code page.
        return mb_check_encoding($contents, 'UTF-8') ? $contents : mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    /**
     * Reverts the leading apostrophe added by EscapeFormula on our own downloads.
     */
    private function unescapeFormula(string $value): string
    {
        return isset($value[1]) && $value[0] === "'" && in_array($value[1], self::FORMULA_CHARACTERS, true)
            ? substr($value, 1)
            : $value;
    }
}
