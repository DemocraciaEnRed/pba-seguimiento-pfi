<?php

namespace Database\Seeders;

use App\Goal;
use App\ImageFile;
use App\Objective;
use App\Organization;
use App\PfiCategory;
use App\Report;
use App\Role;
use App\Services\Reports\ReviewStatus;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fictitious people, goals and reports that cover every section of the Mesa de trabajo, for visual review.
 *
 * Load it on a dedicated database, never on one with real data:
 * CACHE_STORE=array DB_DATABASE=participes_demo_mesa php artisan migrate:fresh --seed --seeder=WorkDeskDemoSeeder
 */
class WorkDeskDemoSeeder extends Seeder
{
    public const PASSWORD = 'participes';

    /** @var array<string, array{name: string, surname: string, email: string, admin?: bool, pfi?: string}> */
    public const PEOPLE = [
        'admin' => ['name' => 'Ana', 'surname' => 'Administración', 'email' => 'ana.admin@demo.test', 'admin' => true],
        'rosa' => ['name' => 'Rosa', 'surname' => 'Responsable', 'email' => 'rosa.responsable@demo.test', 'pfi' => 'Integrante MISTO'],
        'raul' => ['name' => 'Raúl', 'surname' => 'Corresponsable', 'email' => 'raul.corresponsable@demo.test', 'pfi' => 'Ente'],
        'paula' => ['name' => 'Paula', 'surname' => 'Reportera', 'email' => 'paula.reportera@demo.test', 'pfi' => 'Organismos MISP'],
        'diego' => ['name' => 'Diego', 'surname' => 'Reportero', 'email' => 'diego.reportero@demo.test', 'pfi' => 'Empresa'],
        'sofia' => ['name' => 'Sofía', 'surname' => 'Sin Metas', 'email' => 'sofia.sinmetas@demo.test', 'pfi' => 'Integrante MISTO'],
        'carlos' => ['name' => 'Carlos', 'surname' => 'Ciudadano', 'email' => 'carlos.ciudadano@demo.test'],
        'ernesto' => ['name' => 'Ernesto', 'surname' => 'Exintegrante', 'email' => 'ernesto.exintegrante@demo.test'],
    ];

    /** @var array<string, User> */
    private array $people = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('La demo de la Mesa de trabajo no se carga en producción.');
        }

        if (User::query()->where('email', self::PEOPLE['admin']['email'])->exists()) {
            throw new RuntimeException('La demo ya está cargada. Para regenerarla, recreá la base con migrate:fresh.');
        }

        if (! Role::query()->exists()) {
            $this->call([SettingsTableSeeder::class, FaqTableSeeder::class, RoleTableSeeder::class]);
        }

        // People go first so the base structure is authored by the demo administrator.
        $this->seedPeople();

        if (! Objective::query()->exists()) {
            $this->call([BaseDataAppSeeder::class, InitiativesSeeder::class]);
        }

        $this->seedGoalsAndReports();
    }

    private function seedPeople(): void
    {
        $userRole = Role::query()->where('name', 'user')->firstOrFail();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        foreach (self::PEOPLE as $key => $person) {
            $user = new User();
            $user->forceFill([
                'name' => $person['name'],
                'surname' => $person['surname'],
                'email' => $person['email'],
                'email_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'remember_token' => Str::random(10),
            ])->save();
            $user->roles()->attach($userRole);

            if ($person['admin'] ?? false) {
                $user->roles()->attach($adminRole);
            }

            $this->people[$key] = $user;
        }

        foreach (self::PEOPLE as $key => $person) {
            if (! isset($person['pfi'])) {
                continue;
            }

            $this->people[$key]->pfiMembership()->create(['added_by' => $this->people['admin']->id]);
            $this->people[$key]->pfiCategories()->attach(PfiCategory::query()->firstOrCreate(['name' => $person['pfi']]));
        }
    }

    private function seedGoalsAndReports(): void
    {
        [$procedures, $openData, $citizenService] = $this->pickObjectives();
        [$schools, $citizenOffice, $openDataOffice, $municipalities] = $this->seedOrganizations();
        ['rosa' => $rosa, 'raul' => $raul, 'paula' => $paula, 'diego' => $diego, 'ernesto' => $ernesto] = $this->people;

        $digitalization = $this->goal($procedures, 'Digitalizar los trámites de habilitación de obras', 'public', [$rosa], [$paula, $diego], $openDataOffice, [$municipalities]);
        $this->published($digitalization, $paula, 'Relevamiento de los trámites del circuito de habilitación', validator: $rosa, submittedDaysAgo: 30);
        $this->pending($digitalization, $ernesto, 'Encuesta de satisfacción a profesionales', submittedDaysAgo: 9, attendant: $diego);
        $this->pending($digitalization, $paula, 'Primer lote de formularios digitalizados', submittedDaysAgo: 6);
        $this->returned($digitalization, $diego, 'Prueba piloto con tres municipios', reviewer: $rosa, reason: 'Falta indicar qué municipios participaron y adjuntar el informe de la prueba.', submittedDaysAgo: 5, reviewedDaysAgo: 2);
        $this->validated($digitalization, $paula, 'Mesa de ayuda para profesionales en funcionamiento', validator: $rosa, submittedDaysAgo: 4, validatedDaysAgo: 1);

        $training = $this->goal($procedures, 'Capacitar a equipos municipales en integridad', 'internal', [$rosa, $raul], [$paula], $municipalities);
        $this->closed($training, $paula, 'Convocatoria a municipios cerrada', validator: $rosa, submittedDaysAgo: 15, closedDaysAgo: 10);
        $this->returned($training, $paula, 'Asistencia al primer encuentro regional', reviewer: $raul, reason: 'Agregá el listado de municipios presentes.', submittedDaysAgo: 7, reviewedDaysAgo: 3);
        $this->validated($training, $paula, 'Materiales del curso actualizados', validator: $raul, submittedDaysAgo: 6, validatedDaysAgo: 1);
        $this->pending($training, $paula, 'Segundo encuentro regional de capacitación', submittedDaysAgo: 3);

        $maintenance = $this->goal($procedures, 'Mantenimiento preventivo de edificios escolares', 'public', [$rosa], [$paula], $schools);
        foreach (range(1, 7) as $school) {
            $this->pending($maintenance, $paula, "Visita técnica a la Escuela N° {$school}", submittedDaysAgo: 8 - $school);
        }

        $contracts = $this->goal($openData, 'Publicar las contrataciones en formato abierto', 'public', [], [$diego], $openDataOffice);
        $this->pending($contracts, $diego, 'Primer conjunto de datos publicado', submittedDaysAgo: 4);
        $this->goal($openData, 'Relevar el estado de las obras con inspección', 'public', [$raul], []);
        $this->goal($openData, 'Actualizar el registro de proveedores', 'public', [], []);
        $this->goal($openData, 'Auditar los convenios firmados en 2025', 'public', [], [], status: 'reached');

        $offices = $this->goal($citizenService, 'Ampliar la red de puntos de atención ciudadana', 'public', [$raul], [$rosa, $diego], $citizenOffice, [$municipalities]);
        $this->returned($offices, $ernesto, 'Relevamiento de locales disponibles', reviewer: $raul, reason: 'Completá la dirección de cada local relevado.', submittedDaysAgo: 15, reviewedDaysAgo: 12);
        $this->returned($offices, $rosa, 'Horarios extendidos los sábados', reviewer: $raul, reason: 'Indicá desde qué fecha rige el nuevo horario.', submittedDaysAgo: 4, reviewedDaysAgo: 1);
        $this->pending($offices, $rosa, 'Apertura del punto de atención en Berisso', submittedDaysAgo: 2);

        $complaints = $this->goal($citizenService, 'Reducir el tiempo de respuesta de los reclamos', 'internal', [$rosa], [$diego], $citizenOffice);
        $this->pending($complaints, $diego, 'Tablero de reclamos por municipio', submittedDaysAgo: 20);
        $this->validated($complaints, $diego, 'Protocolo de derivación de reclamos', validator: $rosa, submittedDaysAgo: 5, validatedDaysAgo: 2);
    }

    /**
     * Two objectives of the first visible axis and one of another axis, so the Eje and Objetivo filters have something to split.
     *
     * @return array{Objective, Objective, Objective}
     */
    private function pickObjectives(): array
    {
        $objectives = Objective::query()
            ->with('strategicObjective')
            ->where('hidden', false)
            ->whereHas('strategicObjective')
            ->orderBy('id')
            ->get();
        $axisOf = fn (Objective $objective): int => (int) $objective->strategicObjective->category_id;
        $first = $objectives->first();
        $sameAxis = $first ? $objectives->first(fn (Objective $objective): bool => $objective->isNot($first) && $axisOf($objective) === $axisOf($first)) : null;
        $otherAxis = $first ? $objectives->first(fn (Objective $objective): bool => $axisOf($objective) !== $axisOf($first)) : null;

        if (! $first || ! $sameAxis || ! $otherAxis) {
            throw new RuntimeException('La demo necesita dos ejes con objetivos visibles y al menos dos objetivos en el primero.');
        }

        return [$first, $sameAxis, $otherAxis];
    }

    /**
     * @return list<Organization>
     */
    private function seedOrganizations(): array
    {
        return array_map(function (string $name): Organization {
            $organization = new Organization();
            $organization->name = $name;
            $organization->description = 'Organización ficticia de la demo de la Mesa de trabajo.';
            $organization->save();

            $logo = new ImageFile();
            $logo->name = 'default-organization.png';
            $logo->size = '100';
            $logo->mime = 'image/png';
            $logo->path = '/img/default-organization.png';
            $organization->logo()->save($logo);

            return $organization;
        }, [
            'Dirección de Infraestructura Escolar',
            'Dirección de Atención Ciudadana',
            'Oficina de Datos Abiertos',
            'Consejo de Municipios',
        ]);
    }

    /**
     * @param  list<User>  $responsibles
     * @param  list<User>  $reporters
     * @param  list<Organization>  $participants
     */
    private function goal(Objective $objective, string $title, string $visibility, array $responsibles, array $reporters, ?Organization $responsibleOrganization = null, array $participants = [], string $status = 'ongoing'): Goal
    {
        $goal = new Goal();
        $goal->forceFill([
            'title' => $title,
            'status' => $status,
            'visibility' => $visibility,
            'objective_id' => $objective->id,
            'responsible_organization_id' => $responsibleOrganization?->id,
        ])->save();

        $assignedBy = ['assigned_by' => $this->people['admin']->id];
        foreach ($responsibles as $responsible) {
            $goal->responsibles()->attach($responsible->id, $assignedBy);
        }
        foreach ($reporters as $reporter) {
            $goal->reporters()->attach($reporter->id, $assignedBy);
        }
        $goal->participantOrganizations()->attach(array_map(fn (Organization $organization): int => $organization->id, $participants));

        return $goal;
    }

    private function pending(Goal $goal, User $author, string $title, int $submittedDaysAgo, ?User $attendant = null): Report
    {
        return $this->report($goal, $author, $title, ReviewStatus::Pending, $submittedDaysAgo, ['attendant_id' => ($attendant ?? $author)->id]);
    }

    private function returned(Goal $goal, User $author, string $title, User $reviewer, string $reason, int $submittedDaysAgo, int $reviewedDaysAgo): Report
    {
        return $this->report($goal, $author, $title, ReviewStatus::Returned, $submittedDaysAgo, [
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()->subDays($reviewedDaysAgo),
            'return_reason' => $reason,
            'review_version' => 2,
        ]);
    }

    private function validated(Goal $goal, User $author, string $title, User $validator, int $submittedDaysAgo, int $validatedDaysAgo): Report
    {
        return $this->report($goal, $author, $title, ReviewStatus::Validated, $submittedDaysAgo, [
            'validated_by' => $validator->id,
            'validated_at' => now()->subDays($validatedDaysAgo),
            'review_version' => 2,
        ]);
    }

    private function closed(Goal $goal, User $author, string $title, User $validator, int $submittedDaysAgo, int $closedDaysAgo): Report
    {
        return $this->report($goal, $author, $title, ReviewStatus::Validated, $submittedDaysAgo, [
            'validated_by' => $validator->id,
            'validated_at' => now()->subDays($closedDaysAgo + 1),
            'closed_by' => $this->people['admin']->id,
            'closed_at' => now()->subDays($closedDaysAgo),
            'review_version' => 3,
        ]);
    }

    private function published(Goal $goal, User $author, string $title, User $validator, int $submittedDaysAgo): Report
    {
        return $this->report($goal, $author, $title, ReviewStatus::Approved, $submittedDaysAgo, [
            'validated_by' => $validator->id,
            'validated_at' => now()->subDays($submittedDaysAgo - 2),
            'reviewed_by' => $this->people['admin']->id,
            'reviewed_at' => now()->subDays($submittedDaysAgo - 3),
            'organizations_snapshot' => $goal->organizationsSnapshot(),
            'review_version' => 3,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function report(Goal $goal, User $author, string $title, ReviewStatus $status, int $submittedDaysAgo, array $attributes): Report
    {
        $submittedAt = now()->subDays($submittedDaysAgo);
        $report = new Report();
        $report->forceFill(array_replace([
            'goal_id' => $goal->id,
            'author_id' => $author->id,
            'attendant_id' => $author->id,
            'type' => 'post',
            'title' => $title,
            'content' => 'Reporte ficticio de la demo de la Mesa de trabajo. Resume las acciones del período, los resultados obtenidos y los próximos pasos acordados con el equipo.',
            'tags' => [],
            'date' => $submittedAt,
            'review_status' => $status,
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ], $attributes))->save();

        return $report;
    }
}
