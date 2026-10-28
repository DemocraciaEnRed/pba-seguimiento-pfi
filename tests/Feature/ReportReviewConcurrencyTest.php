<?php

namespace Tests\Feature;

use App\Goal;
use App\Report;
use App\User;
use App\Services\Reports\ReportReview;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Throwable;

class ReportReviewConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function afterRefreshingDatabase(): void
    {
        // The legacy base migration drops parent tables before their children.
        $this->beforeApplicationDestroyed(fn () => Schema::disableForeignKeyConstraints());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(function_exists('pcntl_fork'), 'Estas pruebas requieren pcntl para verificar concurrencia real.');
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_testing', DB::getDatabaseName());
        foreach ([1, 2] as $id) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Usuario', 'surname' => 'Prueba', 'email' => $id.'@example.com', 'password' => 'hash']);
        }
        $role = DB::table('roles')->insertGetId(['name' => 'admin', 'description' => 'Administrador']);
        DB::table('role_user')->insert(['role_id' => $role, 'user_id' => 1]);
        $category = DB::table('categories')->insertGetId(['title' => 'Eje', 'icon' => 'fas fa-circle', 'color' => '#123456', 'order' => 1]);
        $strategic = DB::table('strategic_objectives')->insertGetId(['codigo' => 'OE1', 'title' => 'Estratégico', 'category_id' => $category]);
        DB::table('objectives')->insert(['id' => 1, 'title' => 'Objetivo', 'content' => 'Contenido', 'author_id' => 1, 'strategic_objective_id' => $strategic]);
        DB::table('objective_user')->insert(['objective_id' => 1, 'user_id' => 2, 'role' => 'reporter']);
        DB::table('goals')->insert(['id' => 1, 'objective_id' => 1, 'title' => 'Meta', 'status' => 'ongoing', 'measurement_mode' => 'simple', 'indicator_goal' => 100, 'indicator_progress' => 40]);
        Notification::fake();
    }

    public function test_two_simultaneous_approvals_apply_a_report_once(): void
    {
        $report = $this->proposal();
        $data = $this->approvalData($report);
        $results = $this->race([
            fn () => $this->approve($report->id, $data),
            fn () => $this->approve($report->id, $data),
        ]);
        sort($results);
        $this->assertSame(['conflict', 'ok'], $results);
        $this->assertSame(45.0, Goal::findOrFail(1)->indicator_progress);
        $this->assertTrue($report->fresh()->isApproved());
    }

    public function test_distinct_increments_require_reviewing_the_changed_context_then_preserve_both(): void
    {
        $first = $this->proposal();
        $second = $this->proposal();
        $firstData = $this->approvalData($first);
        $secondData = $this->approvalData($second);
        $results = $this->race([
            fn () => $this->approve($first->id, $firstData),
            fn () => $this->approve($second->id, $secondData),
        ]);
        sort($results);
        $this->assertSame(['conflict', 'ok'], $results);
        $pending = Report::where('review_status', 'pending')->sole();
        $this->approve($pending->id, $this->approvalData($pending));
        $this->assertSame(50.0, Goal::findOrFail(1)->indicator_progress);
        $this->assertSame(2, Report::approved()->count());
    }

    public function test_editing_while_approving_never_applies_an_unreviewed_version(): void
    {
        $report = $this->proposal();
        $data = $this->approvalData($report);
        $results = $this->race([
            fn () => $this->approve($report->id, $data),
            fn () => app(ReportReview::class)->update(Report::findOrFail($report->id), User::findOrFail(2), new Request([
                'review_version' => 1, 'title' => 'Cambio simultáneo', 'content' => 'Contenido', 'date' => '2026-08-01', 'progress' => 20,
            ])),
        ]);
        sort($results);
        $this->assertSame(['conflict', 'ok'], $results);
        $fresh = $report->fresh();
        $this->assertSame($fresh->isApproved() ? 5.0 : 20.0, $fresh->progress);
        $this->assertSame($fresh->isApproved() ? 45.0 : 40.0, Goal::findOrFail(1)->indicator_progress);
    }

    private function proposal(): Report
    {
        $id = DB::table('reports')->insertGetId([
            'author_id' => 2, 'goal_id' => 1, 'type' => 'progress', 'title' => 'Propuesta',
            'content' => 'Contenido', 'date' => '2026-08-01', 'progress' => 5,
        ]);
        return Report::findOrFail($id);
    }

    /** @return array{review_version: int, context: string} */
    private function approvalData(Report $report): array
    {
        return ['review_version' => $report->review_version, 'context' => app(ReportReview::class)->context($report)];
    }

    /** @param array{review_version: int, context: string} $data */
    private function approve(int $id, array $data): void
    {
        app(ReportReview::class)->transition(Report::findOrFail($id), User::findOrFail(1), new Request($data), 'approved');
    }

    /** @param list<\Closure(): mixed> $operations
     *  @return list<string>
     */
    private function race(array $operations): array
    {
        DB::disconnect();
        $workers = [];
        try {
            foreach ($operations as $operation) {
                $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $this->assertNotFalse($pair);
                $path = tempnam(sys_get_temp_dir(), 'report-review-race-');
                $this->assertNotFalse($path);
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    pcntl_alarm(15);
                    fclose($pair[0]);
                    fread($pair[1], 1);
                    DB::purge();
                    try {
                        $operation();
                        $result = 'ok';
                    } catch (ValidationException|HttpException $exception) {
                        $result = $exception instanceof ValidationException || $exception->getStatusCode() === 403
                            ? 'conflict' : $exception->getMessage();
                    } catch (Throwable $exception) {
                        $result = get_class($exception).': '.$exception->getMessage();
                    }
                    file_put_contents($path, $result);
                    fclose($pair[1]);
                    exit(0);
                }
                fclose($pair[1]);
                $workers[] = ['pid' => $pid, 'socket' => $pair[0], 'path' => $path];
            }
            DB::beginTransaction();
            Goal::whereKey(1)->lockForUpdate()->firstOrFail();
            foreach ($workers as $worker) {
                fwrite($worker['socket'], 'x');
            }
            usleep(200000);
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                pcntl_waitpid($worker['pid'], $status);
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'El proceso concurrente no terminó correctamente.');
                $results[] = file_get_contents($worker['path']);
            }
            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                fclose($worker['socket']);
                unlink($worker['path']);
            }
        }
    }
}
