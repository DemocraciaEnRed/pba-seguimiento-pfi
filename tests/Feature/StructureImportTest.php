<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Imports\Structure\ImportAction;
use App\Imports\Structure\StructureImportColumns as Column;
use App\Imports\Structure\StructureImportPlan;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\Indicators\Direction;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\Services\Indicators\MeasurementMode;
use App\Services\Indicators\PeriodType;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use League\Csv\Writer;
use Tests\TestCase;

class StructureImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private StrategicObjective $strategicObjective;

    private Objective $objective;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-08-15 10:00:00');

        $this->admin = $this->createUser('admin@example.com');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $this->category = new Category();
        $this->category->title = 'Transparencia';
        $this->category->icon = 'fas fa-circle';
        $this->category->color = '#000000';
        $this->category->order = 1;
        $this->category->save();

        $this->strategicObjective = new StrategicObjective();
        $this->strategicObjective->codigo = 'OE1';
        $this->strategicObjective->title = 'Fortalecer la transparencia';
        $this->strategicObjective->category()->associate($this->category);
        $this->strategicObjective->save();

        $this->objective = $this->createObjective('OG1', 'Publicar datos abiertos');
    }

    public function test_admin_sees_the_import_screen_with_the_columns_help(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.import'))
            ->assertOk()
            ->assertSee('Planilla precargada')
            ->assertSee(Column::MODE)
            ->assertSee('Sin indicador numérico · Valor acumulado · Por períodos');
    }

    public function test_only_admins_can_import(): void
    {
        $this->actingAs($this->createUser('manager@example.com'))
            ->get(route('admin.import'))
            ->assertForbidden();
    }

    public function test_the_upload_must_be_a_csv_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.import.upload'), ['file' => UploadedFile::fake()->image('planilla.png')])
            ->assertSessionHasErrors('file');
    }

    public function test_the_preview_needs_an_uploaded_file(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.import.preview'))
            ->assertRedirect(route('admin.import'));
    }

    public function test_the_preview_explains_the_actions_without_saving_and_confirm_applies_them(): void
    {
        $existingGoal = $this->createGoal('Manual aprobado');

        $this->upload($this->csv([
            [Column::CATEGORY => 'Transparencia', Column::STRATEGIC_OBJECTIVE => 'Nuevo objetivo estratégico', Column::OBJECTIVE => 'Nuevo objetivo', Column::OBJECTIVE_DESCRIPTION => 'Descripción nueva',
                Column::GOAL => 'Plazo de respuesta', Column::STATUS => 'En progreso', Column::MODE => 'Por períodos', Column::INDICATOR => 'Días promedio', Column::UNIT => 'días',
                Column::DIRECTION => 'Menor es mejor', Column::NATURE => 'Promedio', Column::PERIOD_TYPE => 'Trimestral', Column::START_YEAR => '2027', Column::START_MONTH => '1', Column::PERIOD_COUNT => '4',
                Column::periodTarget(1) => '15', Column::periodTarget(2) => '12,5', Column::periodTarget(4) => '10'],
            [Column::GOAL => 'Datasets publicados', Column::STATUS => 'En progreso', Column::MODE => 'Valor acumulado', Column::INDICATOR => 'Datasets', Column::UNIT => 'datasets', Column::GOAL_VALUE => '50'],
            [Column::STRATEGIC_OBJECTIVE_CODE => 'OE1', Column::STRATEGIC_OBJECTIVE => 'Fortalecer la transparencia', Column::OBJECTIVE_CODE => 'OG1', Column::OBJECTIVE => 'Publicar datos abiertos y reutilizables',
                Column::GOAL => 'Manual aprobado', Column::STATUS => 'Inactiva', Column::MODE => 'Sin indicador numérico'],
        ]))->assertRedirect(route('admin.import.preview'));

        $response = $this->actingAs($this->admin)->get(route('admin.import.preview'))->assertOk()->assertSee('Confirmar importación');
        $plan = $response->viewData('plan');

        $this->assertSame([], $plan->errors);
        $this->assertSame([
            'Objetivos estratégicos' => ['create' => 1, 'update' => 0, 'unchanged' => 1],
            'Objetivos' => ['create' => 1, 'update' => 1, 'unchanged' => 0],
            'Metas' => ['create' => 2, 'update' => 1, 'unchanged' => 0],
        ], $plan->summary());
        $this->assertSame([Column::STATUS => ['En progreso', 'Inactiva']], $plan->strategicObjectives[1]->children[0]->children[0]->changes);
        $this->assertSame(1, StrategicObjective::count());
        $this->assertSame(1, Goal::count());

        $this->actingAs($this->admin)
            ->post(route('admin.import.confirm'))
            ->assertRedirect(route('admin.import'))
            ->assertSessionHas('success');

        $newObjective = Objective::where('title', 'Nuevo objetivo')->sole();
        $this->assertSame(['OE2', 'Nuevo objetivo estratégico'], [$newObjective->strategicObjective->codigo, $newObjective->strategicObjective->title]);
        $this->assertSame(['OG2', 'Descripción nueva', 1, $this->admin->id], [$newObjective->codigo, $newObjective->content, (int) $newObjective->hidden, $newObjective->author_id]);

        $periodicGoal = $newObjective->goals()->where('title', 'Plazo de respuesta')->sole();
        $this->assertSame(MeasurementMode::Periodic, $periodicGoal->measurement_mode);
        $this->assertSame([Direction::LowerIsBetter, PeriodType::Quarterly, '2027-01'], [$periodicGoal->indicator_direction, $periodicGoal->period_type, $periodicGoal->period_start->format('Y-m')]);
        $this->assertSame([1 => 15.0, 2 => 12.5, 3 => null, 4 => 10.0], $periodicGoal->periods->pluck('target_value', 'number')->all());

        $simpleGoal = $newObjective->goals()->where('title', 'Datasets publicados')->sole();
        $this->assertSame([MeasurementMode::Simple, 50.0, 0.0], [$simpleGoal->measurement_mode, $simpleGoal->indicator_goal, $simpleGoal->indicator_progress]);

        $this->assertSame('Publicar datos abiertos y reutilizables', $this->objective->fresh()->title);
        $this->assertSame('Descripción', $this->objective->fresh()->content);
        $this->assertSame('inactive', $existingGoal->fresh()->status);
        $this->assertSame([], Storage::disk('local')->files('imports'));
    }

    public function test_it_accepts_csv_saved_by_excel_in_spanish(): void
    {
        $csv = $this->csv([
            [Column::CATEGORY => 'transparencia', Column::STRATEGIC_OBJECTIVE => 'Capacitar', Column::OBJECTIVE => 'Formación continua', Column::OBJECTIVE_DESCRIPTION => 'Descripción',
                Column::GOAL => 'Personas capacitadas', Column::STATUS => 'en progreso', Column::MODE => 'por periodos', Column::INDICATOR => 'Personas', Column::UNIT => 'personas',
                Column::DIRECTION => 'mayor', Column::NATURE => 'suma', Column::PERIOD_TYPE => 'semestral', Column::START_YEAR => '2027', Column::START_MONTH => 'julio', Column::PERIOD_COUNT => '2',
                Column::periodTarget(1) => '1.234,5', Column::periodTarget(2) => '10'],
        ], delimiter: ';');

        $this->upload(mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));
        $this->assertSame([], $this->preview()->errors);

        $this->actingAs($this->admin)->post(route('admin.import.confirm'))->assertRedirect(route('admin.import'));

        $goal = Goal::where('title', 'Personas capacitadas')->sole();
        $this->assertSame(['2027-07', Direction::HigherIsBetter], [$goal->period_start->format('Y-m'), $goal->indicator_direction]);
        $this->assertSame([1 => 1234.5, 2 => 10.0], $goal->periods->pluck('target_value', 'number')->all());
        $this->assertSame('Formación continua', $goal->objective->title);
    }

    public function test_errors_are_reported_by_row_and_column_and_block_the_import(): void
    {
        $this->upload($this->csv([
            [Column::CATEGORY => 'Inexistente', Column::STRATEGIC_OBJECTIVE => 'OE', Column::OBJECTIVE => 'Objetivo', Column::OBJECTIVE_DESCRIPTION => 'Descripción'],
            [Column::CATEGORY => 'Transparencia', Column::STRATEGIC_OBJECTIVE => 'Nuevo', Column::OBJECTIVE => 'Sin descripción'],
            [Column::CATEGORY => 'Transparencia', Column::STRATEGIC_OBJECTIVE_CODE => 'OE1', Column::STRATEGIC_OBJECTIVE => 'Fortalecer la transparencia', Column::OBJECTIVE_CODE => 'OG1', Column::OBJECTIVE => 'Publicar datos abiertos',
                Column::GOAL => 'Meta nueva', Column::STATUS => 'Alcanzada', Column::MODE => 'Sin indicador numérico'],
            [Column::GOAL => 'Modo inválido', Column::STATUS => 'En progreso', Column::MODE => 'Mensualmente'],
            [Column::GOAL => 'Períodos de más', Column::STATUS => 'En progreso', Column::MODE => 'Por períodos', Column::INDICATOR => 'Indicador', Column::UNIT => 'u',
                Column::DIRECTION => 'Mayor', Column::NATURE => 'Suma', Column::PERIOD_TYPE => 'Anual', Column::START_YEAR => '2027', Column::START_MONTH => '1', Column::PERIOD_COUNT => '4',
                Column::periodTarget(1) => '1', Column::periodTarget(5) => '3'],
            [Column::GOAL => 'Acumulada sin valor', Column::STATUS => 'En progreso', Column::MODE => 'Valor acumulado', Column::INDICATOR => 'Indicador', Column::UNIT => 'u'],
        ], periodColumns: 5));

        $response = $this->actingAs($this->admin)->get(route('admin.import.preview'))
            ->assertOk()
            ->assertSee('Fila 2')
            ->assertDontSee('Confirmar importación');

        $this->assertSame([
            [2, Column::CATEGORY],
            [3, Column::OBJECTIVE_DESCRIPTION],
            [4, Column::STATUS],
            [5, Column::MODE],
            [6, Column::periodTarget(5)],
            [7, Column::GOAL_VALUE],
        ], array_map(fn (array $error): array => [$error['line'], $error['column']], $response->viewData('plan')->errors));

        $this->actingAs($this->admin)
            ->post(route('admin.import.confirm'))
            ->assertRedirect(route('admin.import.preview'))
            ->assertSessionHas('warning');

        $this->assertSame(1, StrategicObjective::count());
        $this->assertSame(0, Goal::count());
    }

    public function test_a_goal_with_progress_keeps_its_structure_but_accepts_other_changes(): void
    {
        $goal = $this->createPeriodicGoal('Plazo de emisión');
        $report = new Report();
        $report->title = 'Avance';
        $report->type = 'progress';
        $report->content = 'Contenido';
        $report->date = '2026-04-05';
        $report->measured_value = 20;
        $report->author()->associate($this->admin);
        $report->goal()->associate($goal);
        $report->period()->associate($goal->periods->firstWhere('number', 1));
        $report->save();

        $row = [Column::CATEGORY => 'Transparencia', Column::STRATEGIC_OBJECTIVE_CODE => 'OE1', Column::STRATEGIC_OBJECTIVE => 'Fortalecer la transparencia',
            Column::OBJECTIVE_CODE => 'OG1', Column::OBJECTIVE => 'Publicar datos abiertos', Column::GOAL_ID => (string) $goal->id,
            Column::GOAL => 'Plazo de emisión', Column::STATUS => 'En progreso', Column::MODE => 'Por períodos', Column::INDICATOR => 'Días', Column::UNIT => 'días',
            Column::DIRECTION => 'Menor es mejor', Column::NATURE => 'Promedio', Column::PERIOD_TYPE => 'Semestral', Column::START_YEAR => '2026', Column::START_MONTH => '1', Column::PERIOD_COUNT => '4',
            Column::periodTarget(1) => '27', Column::periodTarget(2) => '27', Column::periodTarget(3) => '25', Column::periodTarget(4) => '21'];

        $this->upload($this->csv([$row]));
        $this->assertSame([[2, Column::PERIOD_TYPE]], array_map(fn (array $error): array => [$error['line'], $error['column']], $this->preview()->errors));

        $this->upload($this->csv([array_merge($row, [Column::PERIOD_TYPE => 'Trimestral', Column::GOAL => 'Plazo de emisión de certificados', Column::periodTarget(2) => '26'])]));
        $plan = $this->preview();
        $goalItem = $plan->strategicObjectives[0]->children[0]->children[0];

        $this->assertSame([], $plan->errors);
        $this->assertSame(ImportAction::Update, $goalItem->action);
        $this->assertSame([Column::GOAL => ['Plazo de emisión', 'Plazo de emisión de certificados'], Column::periodTarget(2) => ['27', '26']], $goalItem->changes);

        $this->actingAs($this->admin)->post(route('admin.import.confirm'))->assertRedirect(route('admin.import'));

        $this->assertSame('Plazo de emisión de certificados', $goal->fresh()->title);
        $this->assertSame(26.0, $goal->fresh()->periods->firstWhere('number', 2)->target_value);
    }

    public function test_ambiguous_titles_ask_for_the_code(): void
    {
        $this->createObjective('OG2', 'Duplicado');
        $this->createObjective('OG3', 'Duplicado');

        $this->upload($this->csv([
            [Column::CATEGORY => 'Transparencia', Column::STRATEGIC_OBJECTIVE_CODE => 'OE1', Column::STRATEGIC_OBJECTIVE => 'Fortalecer la transparencia', Column::OBJECTIVE => 'Duplicado'],
            [Column::CATEGORY => 'Transparencia', Column::STRATEGIC_OBJECTIVE_CODE => 'OE1', Column::STRATEGIC_OBJECTIVE => 'Fortalecer la transparencia', Column::OBJECTIVE_CODE => 'OG3', Column::OBJECTIVE => 'Duplicado'],
        ]));

        $plan = $this->preview();

        $this->assertSame([[2, Column::OBJECTIVE]], array_map(fn (array $error): array => [$error['line'], $error['column']], $plan->errors));
        $this->assertStringContainsString('Código objetivo', $plan->errors[0]['message']);
    }

    public function test_the_prefilled_template_uploads_without_changes(): void
    {
        $this->createGoal('+Manual aprobado');
        $simpleGoal = new Goal();
        $simpleGoal->title = 'Datasets publicados';
        $simpleGoal->status = 'delayed';
        $simpleGoal->source = 'Portal';
        $simpleGoal->objective()->associate($this->objective);
        app(GoalIndicatorConfigurator::class)->apply($simpleGoal, [
            'measurement_mode' => 'simple', 'indicator' => 'Datasets', 'indicator_unit' => 'datasets', 'indicator_frequency' => 'Mensual',
            'indicator_goal' => 50, 'indicator_progress' => 12.75,
        ]);
        $this->createPeriodicGoal('Plazo de emisión', [1 => 27, 2 => 27.5, 3 => null, 4 => 21]);
        $this->createObjective('OG2', 'Objetivo sin metas');
        $emptyStrategicObjective = new StrategicObjective();
        $emptyStrategicObjective->codigo = 'OE2';
        $emptyStrategicObjective->title = 'Objetivo estratégico sin objetivos';
        $emptyStrategicObjective->category()->associate($this->category);
        $emptyStrategicObjective->save();

        $template = $this->actingAs($this->admin)->get(route('admin.import.template'))->assertOk()->streamedContent();

        $this->upload($template);
        $response = $this->actingAs($this->admin)->get(route('admin.import.preview'))->assertSee('El archivo no tiene cambios para aplicar.');
        $plan = $response->viewData('plan');

        $this->assertSame([], $plan->errors);
        $this->assertFalse($plan->hasChanges());
        $this->assertSame(['create' => 0, 'update' => 0, 'unchanged' => 3], $plan->summary()['Metas']);
        $this->assertSame(['create' => 0, 'update' => 0, 'unchanged' => 2], $plan->summary()['Objetivos estratégicos']);
    }

    public function test_the_example_spreadsheet_is_valid(): void
    {
        $example = $this->actingAs($this->admin)->get(route('admin.import.example'))->assertOk()->streamedContent();

        $this->upload($example);
        $plan = $this->preview();

        $this->assertSame([], $plan->errors);
        $this->assertSame([
            'Objetivos estratégicos' => ['create' => 1, 'update' => 0, 'unchanged' => 0],
            'Objetivos' => ['create' => 2, 'update' => 0, 'unchanged' => 0],
            'Metas' => ['create' => 4, 'update' => 0, 'unchanged' => 0],
        ], $plan->summary());
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function csv(array $rows, int $periodColumns = 4, string $delimiter = ','): string
    {
        $headings = Column::headings($periodColumns);
        $writer = Writer::from('php://temp', 'r+')->setDelimiter($delimiter)->setEscape('');
        $writer->insertOne($headings);

        foreach ($rows as $row) {
            $writer->insertOne(array_map(fn (string $heading): string => $row[$heading] ?? '', $headings));
        }

        return $writer->toString();
    }

    private function upload(string $contents): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('estructura.csv', $contents),
        ]);
    }

    private function preview(): StructureImportPlan
    {
        return $this->actingAs($this->admin)->get(route('admin.import.preview'))->assertOk()->viewData('plan');
    }

    private function createObjective(string $code, string $title): Objective
    {
        $objective = new Objective();
        $objective->codigo = $code;
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = false;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($this->strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(string $title): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);
        $goal->save();

        return $goal;
    }

    /**
     * @param  array<int, ?float>  $targets
     */
    private function createPeriodicGoal(string $title, array $targets = [1 => 27, 2 => 27, 3 => 25, 4 => 21]): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);

        app(GoalIndicatorConfigurator::class)->apply($goal, [
            'measurement_mode' => 'periodic',
            'indicator' => 'Días',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'quarterly',
            'period_start' => '2026-01',
            'period_count' => count($targets),
            'period_targets' => $targets,
        ]);

        return $goal->load('periods');
    }

    private function createUser(string $email): User
    {
        $user = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
