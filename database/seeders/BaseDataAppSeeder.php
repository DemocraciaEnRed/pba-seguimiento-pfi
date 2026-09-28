<?php

namespace Database\Seeders;

use App\Category;
use App\Objective;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class BaseDataAppSeeder extends Seeder
{
    private const EXPECTED_HEADER = [
        'N° Eje',
        'Eje',
        'Objetivo Estrategico',
        'Objetivo',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/base-data-app.csv');
        $rows = $this->readRowsFromCsv($csvPath);
        $strategicCodesByKey = $this->buildSequentialCodes($rows, fn (array $row): string => $this->strategicKey($row));
        $objectiveCodesByKey = $this->buildSequentialCodes($rows, fn (array $row): string => $this->objectiveKey($row));
        $admin = $this->resolveAdminUser();
        $axisPalette = $this->axisPalette();
        $objectivesHaveCode = Schema::hasColumn('objectives', 'codigo');

        DB::transaction(function () use ($rows, $strategicCodesByKey, $objectiveCodesByKey, $admin, $axisPalette, $objectivesHaveCode): void {
            foreach ($rows as $row) {
                $axisOrder = (int) $row['N° Eje'];

                if (!isset($axisPalette[$axisOrder])) {
                    throw new RuntimeException("No hay configuración de icon/color para el eje {$axisOrder}");
                }

                $category = Category::query()->firstOrNew(['order' => $axisOrder]);
                $category->title = $row['Eje'];
                $category->icon = $axisPalette[$axisOrder]['icon'];
                $category->color = $axisPalette[$axisOrder]['color'];
                $category->save();

                $strategicKey = $this->strategicKey($row);
                $strategicCode = 'OE' . $strategicCodesByKey[$strategicKey];

                $strategicObjective = StrategicObjective::query()->firstOrNew([
                    'category_id' => $category->id,
                    'title' => $row['Objetivo Estrategico'],
                ]);
                $strategicObjective->codigo = $strategicCode;
                $strategicObjective->save();

                $objectiveKey = $this->objectiveKey($row);
                $objectiveCode = 'OG' . $objectiveCodesByKey[$objectiveKey];

                $objective = Objective::query()->firstOrNew([
                    'strategic_objective_id' => $strategicObjective->id,
                    'title' => $row['Objetivo'],
                ]);
                $objective->content = $row['Objetivo'];
                $objective->hidden = false;
                $objective->author()->associate($admin);
                $objective->strategicObjective()->associate($strategicObjective);

                if ($objectivesHaveCode) {
                    $objective->setAttribute('codigo', $objectiveCode);
                }

                $objective->save();
            }
        });
    }

    /**
     * @return array<int, array{N° Eje: string, Eje: string, Objetivo Estrategico: string, Objetivo: string}>
     */
    private function readRowsFromCsv(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new RuntimeException("No se encontró el archivo CSV de datos base en {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo CSV de datos base en {$csvPath}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('El CSV de datos base está vacío.');
            }

            $header[0] = $this->removeUtf8Bom((string) $header[0]);

            if ($header !== self::EXPECTED_HEADER) {
                throw new RuntimeException('Encabezado inválido en CSV de datos base. Esperado: ' . implode(', ', self::EXPECTED_HEADER));
            }

            $rows = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isBlankRow($row)) {
                    continue;
                }

                if (count($row) !== count(self::EXPECTED_HEADER)) {
                    throw new RuntimeException("La fila {$lineNumber} no tiene 4 columnas.");
                }

                $rowData = array_combine(self::EXPECTED_HEADER, array_map(static fn ($value): string => trim((string) $value), $row));

                if ($rowData === false) {
                    throw new RuntimeException("No se pudo interpretar la fila {$lineNumber} del CSV.");
                }

                foreach ($rowData as $column => $value) {
                    if ($value === '') {
                        throw new RuntimeException("La fila {$lineNumber} tiene la columna '{$column}' vacía.");
                    }
                }

                $rows[] = $rowData;
            }

            if ($rows === []) {
                throw new RuntimeException('El CSV de datos base no contiene filas de datos.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function removeUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{N° Eje: string, Eje: string, Objetivo Estrategico: string, Objetivo: string}>  $rows
     * @param  callable(array{N° Eje: string, Eje: string, Objetivo Estrategico: string, Objetivo: string}):string  $keyResolver
     * @return array<string, int>
     */
    private function buildSequentialCodes(array $rows, callable $keyResolver): array
    {
        $keys = [];

        foreach ($rows as $row) {
            $keys[$keyResolver($row)] = true;
        }

        $uniqueKeys = array_keys($keys);
        sort($uniqueKeys, SORT_STRING);

        $codes = [];
        foreach ($uniqueKeys as $index => $key) {
            $codes[$key] = $index + 1;
        }

        return $codes;
    }

    /**
     * @param  array{N° Eje: string, Eje: string, Objetivo Estrategico: string, Objetivo: string}  $row
     */
    private function strategicKey(array $row): string
    {
        return $this->normalizeKey("{$row['N° Eje']}|{$row['Eje']}|{$row['Objetivo Estrategico']}");
    }

    /**
     * @param  array{N° Eje: string, Eje: string, Objetivo Estrategico: string, Objetivo: string}  $row
     */
    private function objectiveKey(array $row): string
    {
        return $this->normalizeKey("{$this->strategicKey($row)}|{$row['Objetivo']}");
    }

    private function normalizeKey(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->trim()->value();
        $collapsed = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? $ascii;

        return trim($collapsed);
    }

    private function resolveAdminUser(): User
    {
        $admin = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', 'admin'))
            ->orderBy('id')
            ->first();

        if ($admin !== null) {
            return $admin;
        }

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator']
        );

        $userRole = Role::query()->firstOrCreate(
            ['name' => 'user'],
            ['description' => 'User']
        );

        $fallbackAdmin = User::query()->orderBy('id')->first();

        if ($fallbackAdmin === null) {
            $fallbackAdmin = new User();
            $fallbackAdmin->name = 'Admin';
            $fallbackAdmin->surname = 'Seeder';
            $fallbackAdmin->email = 'seed-admin@local.test';
            $fallbackAdmin->email_verified_at = now();
            $fallbackAdmin->password = Hash::make(Str::random(40));
            $fallbackAdmin->remember_token = Str::random(10);
            $fallbackAdmin->save();
        }

        if (!$fallbackAdmin->roles()->where('roles.id', $adminRole->id)->exists()) {
            $fallbackAdmin->roles()->attach($adminRole);
        }

        if (!$fallbackAdmin->roles()->where('roles.id', $userRole->id)->exists()) {
            $fallbackAdmin->roles()->attach($userRole);
        }

        return $fallbackAdmin;
    }

    /**
     * @return array<int, array{icon: string, color: string}>
     */
    private function axisPalette(): array
    {
        return [
            1 => ['icon' => 'observatorio-integridad', 'color' => '#003563'],
            2 => ['icon' => 'observatorio-sostenible', 'color' => '#12a24'],
            3 => ['icon' => 'observatorio-genero', 'color' => '#542471'],
            4 => ['icon' => 'observatorio-innovacion', 'color' => '#58c0dc'],
            5 => ['icon' => 'observatorio-planeamiento', 'color' => '#bd0b1d'],
            6 => ['icon' => 'observatorio-procesos', 'color' => '#e31269'],
            7 => ['icon' => 'observatorio-aprendizaje', 'color' => '#8891c3'],
        ];
    }
}
