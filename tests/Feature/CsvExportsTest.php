<?php

namespace Tests\Feature;

use App\Category;
use App\Comment;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\StrategicObjective;
use App\Testimony;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use League\Csv\Bom;
use League\Csv\Reader;
use Tests\TestCase;

class CsvExportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Objective $objective;

    private Goal $goal;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-15 10:00:00');

        $this->admin = $this->createUser('admin@example.com', 'Ana', 'Admin');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#000000';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-1';
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = '=HYPERLINK("https://example.com","Clic")';
        $this->objective->content = 'Descripción';
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->manager = $this->createUser('manager@example.com', 'Mario', 'Gestor');
        $this->objective->members()->attach($this->manager, ['role' => 'manager']);

        $this->goal = new Goal();
        $this->goal->title = 'Emisión de certificados';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        app(GoalIndicatorConfigurator::class)->apply($this->goal, [
            'measurement_mode' => 'periodic',
            'indicator' => 'Días promedio',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'quarterly',
            'period_start' => '2026-01',
            'period_count' => 2,
            'period_targets' => [1 => 27, 2 => 25],
        ]);

        $this->report = new Report();
        $this->report->title = 'Avance, primer trimestre';
        $this->report->type = 'progress';
        $this->report->content = 'Contenido';
        $this->report->date = '2026-04-05';
        $this->report->tags = [];
        $this->report->measured_value = 15.49;
        $this->report->author()->associate($this->manager);
        $this->report->goal()->associate($this->goal);
        $this->report->period()->associate($this->goal->periods()->firstWhere('number', 1));
        $this->report->save();
    }

    public function test_admin_downloads_objectives_as_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.objectives.download'))
            ->assertDownload('20260815-objetivos.csv');

        $records = $this->csvRecords($response);

        $this->assertSame(['Titulo', 'Autor', 'Email', 'Eje', 'Objetivo Estratégico'], array_slice($records[0], 0, 5));
        $this->assertSame(["'=HYPERLINK(\"https://example.com\",\"Clic\")", 'Admin, Ana', 'admin@example.com', 'Eje', 'OE-1 - Objetivo estratégico'], array_slice($records[1], 0, 5));
        $this->assertCount(2, $records);
    }

    public function test_manager_downloads_objective_goals_as_csv(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.download', ['objectiveId' => $this->objective->id]))
            ->assertDownload("20260815-metas-objetivo-{$this->objective->id}.csv");

        $records = $this->csvRecords($response);

        $this->assertSame(['Titulo', 'Estado'], array_slice($records[0], 0, 2));
        $this->assertSame('Emisión de certificados', $records[1][0]);
        $this->assertCount(2, $records);
        $this->assertSame('15/08/2026 10:00:00', array_combine($records[0], $records[1])['Fecha creado']);
    }

    public function test_manager_downloads_objective_subscribers_as_csv(): void
    {
        $subscriber = $this->createUser('suscriptora@example.com', 'Lucía', '@Pérez');
        $this->objective->subscribers()->attach($subscriber);

        $response = $this->actingAs($this->manager)
            ->get(route('objectives.manage.subscribers.download', ['objectiveId' => $this->objective->id]))
            ->assertDownload("20260815-subscriptores-objetivo-{$this->objective->id}.csv");

        $this->assertSame([
            ['Nombre', 'Apellido', 'Email', 'Fecha Subscripción'],
            ['Lucía', "'@Pérez", 'suscriptora@example.com', '15/08/2026'],
        ], $this->csvRecords($response));
    }

    public function test_manager_downloads_goal_reports_as_csv(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.reports.download', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]))
            ->assertDownload("20260815-reportes-meta-{$this->goal->id}-objetivo-{$this->objective->id}.csv");

        $records = $this->csvRecords($response);

        $this->assertSame(['Titulo', 'Autor', 'Autor Email'], array_slice($records[0], 0, 3));
        $this->assertSame(['Avance, primer trimestre', 'Gestor, Mario', 'manager@example.com'], array_slice($records[1], 0, 3));
        $this->assertSame(['15.49', '-'], array_slice($records[1], 14, 2));
        $this->assertSame('15/08/2026 10:00:00', array_combine($records[0], $records[1])['Fecha creado']);
    }

    public function test_manager_downloads_report_comments_with_replies_as_separate_rows(): void
    {
        $comment = $this->createComment('Buen avance');
        $reply = $this->createComment('+1 de acuerdo', $comment);

        $response = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.reports.comments.download', $this->reportRouteParameters()))
            ->assertDownload("20260815-comentarios-reporte-{$this->report->id}.csv");

        $records = $this->csvRecords($response);

        $this->assertCount(3, $records);
        $this->assertSame(['Comentario', (string) $comment->id, '-', 'Gestor, Mario', 'manager@example.com', 'Buen avance'], array_slice($records[1], 0, 6));
        $this->assertSame(['Respuesta', (string) $reply->id, (string) $comment->id, 'Gestor, Mario', 'manager@example.com', "'+1 de acuerdo"], array_slice($records[2], 0, 6));
    }

    public function test_manager_downloads_report_testimonies_as_csv(): void
    {
        $testimony = new Testimony();
        $testimony->report_id = $this->report->id;
        $testimony->user_id = $this->admin->id;
        $testimony->value = true;
        $testimony->save();

        $response = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.reports.testimonies.download', $this->reportRouteParameters()))
            ->assertDownload("20260815-feedbacks-reporte-{$this->report->id}.csv");

        $this->assertSame([
            ['Usuario', 'Usuario Email', 'Feedback'],
            ['Admin, Ana', 'admin@example.com', 'Positivo'],
        ], $this->csvRecords($response));
    }

    public function test_csv_is_utf8_with_bom_and_crlf_line_endings(): void
    {
        $content = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.reports.testimonies.download', $this->reportRouteParameters()))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertSame(Bom::Utf8->value."Usuario,\"Usuario Email\",Feedback\r\n", $content);
    }

    public function test_a_non_member_cannot_download_objective_exports(): void
    {
        $outsider = $this->createUser('outsider@example.com', 'Otro', 'Usuario');

        $this->actingAs($outsider)
            ->get(route('objectives.manage.goals.download', ['objectiveId' => $this->objective->id]))
            ->assertForbidden();
    }

    /**
     * @return list<list<string>>
     */
    private function csvRecords(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith(Bom::Utf8->value, $content);

        $reader = Reader::fromString($content)->setEscape('');

        return iterator_to_array($reader->getRecords(), false);
    }

    /**
     * @return array{objectiveId: int, goalId: int, reportId: int}
     */
    private function reportRouteParameters(): array
    {
        return ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id, 'reportId' => $this->report->id];
    }

    private function createComment(string $content, ?Comment $parent = null): Comment
    {
        $comment = new Comment();
        $comment->content = $content;
        $comment->user()->associate($this->manager);
        $comment->commentable()->associate($this->report);
        $comment->parent_id = $parent?->id;
        $comment->save();

        return $comment;
    }

    private function createUser(string $email, string $name, string $surname): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
