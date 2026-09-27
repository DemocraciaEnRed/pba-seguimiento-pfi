<?php

namespace Tests\Feature;

use App\Services\Csv\CsvReader;
use Illuminate\Validation\ValidationException;
use League\Csv\Bom;
use Tests\TestCase;

class CsvReaderTest extends TestCase
{
    public function test_it_reads_utf8_with_bom_below_title_rows_and_skips_blank_rows(): void
    {
        $path = $this->csvFile(Bom::Utf8->value."Planilla de metas,,\r\n,Agrupación,\r\nEje,Objetivo Estratégico,Meta\r\nGénero,\"Ampliar, sostener\",Una\r\n,,\r\n,,Dos\r\n");

        $rows = (new CsvReader())->read($path, ['Eje', 'Objetivo estratégico']);

        $this->assertSame([
            ['line' => 4, 'values' => ['eje' => 'Género', 'objetivo estrategico' => 'Ampliar, sostener', 'meta' => 'Una']],
            ['line' => 6, 'values' => ['eje' => '', 'objetivo estrategico' => '', 'meta' => 'Dos']],
        ], $rows);
    }

    public function test_it_converts_windows_1252_files_separated_by_semicolons(): void
    {
        $path = $this->csvFile(mb_convert_encoding("Eje;Descripción;Valor\r\nGénero;Año 2027;15,5\r\n", 'Windows-1252', 'UTF-8'));

        $rows = (new CsvReader())->read($path, ['Eje']);

        $this->assertSame([['line' => 2, 'values' => ['eje' => 'Género', 'descripcion' => 'Año 2027', 'valor' => '15,5']]], $rows);
    }

    public function test_it_reverts_the_formula_escaping_of_our_downloads(): void
    {
        $path = $this->csvFile("Eje,Meta\r\n'=HYPERLINK(),'+74%\r\n'Texto,-\r\n");

        $rows = (new CsvReader())->read($path, ['Eje']);

        $this->assertSame(['eje' => '=HYPERLINK()', 'meta' => '+74%'], $rows[0]['values']);
        $this->assertSame(['eje' => "'Texto", 'meta' => '-'], $rows[1]['values']);
    }

    public function test_it_fails_when_the_header_row_is_missing(): void
    {
        $path = $this->csvFile("Nombre,Apellido\r\nAna,Pérez\r\n");

        try {
            (new CsvReader())->read($path, ['Eje', 'Meta']);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Eje, Meta', $exception->errors()['file'][0]);
        }
    }

    private function csvFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
