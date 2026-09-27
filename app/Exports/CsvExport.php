<?php

namespace App\Exports;

interface CsvExport
{
    public function collection(): iterable;

    /**
     * @return list<string>
     */
    public function headings(): array;

    /**
     * Returns one record, or a list of records when a single source row expands into several lines.
     *
     * @return list<mixed>|list<list<mixed>>
     */
    public function map(mixed $row): array;
}
