<?php

namespace App\Http\Controllers;

use App\Exports\ReportCommentsExport;
use App\Exports\ReportTestimoniesExport;
use App\Http\Middleware\AuthorizeReportManagement;
use App\Notifications\DeleteReport;
use App\Notifications\EditReport;
use App\Rules\MatchOldPassword;
use App\Services\Csv\CsvDownload;
use App\Services\Reports\ReportReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportPanelController extends Controller
{
    public function __construct(private ReportReview $review)
    {
        $this->middleware(['auth', 'verified', 'fetch_objective', 'goal_belongs_objective', 'fetch_goal', 'report_belongs_goal', 'fetch_report', 'can_manage_objective', AuthorizeReportManagement::class]);
    }

    public function viewReport(Request $request): \Illuminate\Contracts\View\View
    {
        $request->report->load(['goal', 'milestone', 'period', 'photos']);
        return view('objective.manage.goals.reports.view', [
            'objective' => $request->objective, 'goal' => $request->report->goal, 'report' => $request->report,
            'reviewContext' => $this->review->context($request->report),
            'files' => $request->report->files()->paginate(10, ['*'], 'files_page'),
        ]);
    }

    public function viewEditReport(Request $request): \Illuminate\Contracts\View\View
    {
        $request->goal->load('milestones');
        return view('objective.manage.goals.reports.edit', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report]);
    }

    public function formEditReport(Request $request): RedirectResponse
    {
        if (! $request->report->isApproved()) {
            $this->review->update($request->report, $request->user(), $request);
        } else {
            $data = $request->validate([
                'title' => 'required|string|max:550', 'content' => 'required|string', 'date' => 'required|date',
                'previous_status' => ['nullable', Rule::in(array_keys(\App\Goal::STATUSES))],
                'status' => ['nullable', Rule::in(array_keys(\App\Goal::STATUSES))],
                'previous_progress' => 'nullable|numeric|min:0', 'progress' => 'nullable|numeric|min:0',
                'measured_value' => [Rule::requiredIf($request->goal->isPeriodic() && $request->report->type === 'progress'), 'nullable', 'numeric', 'min:0'],
                'milestone' => 'nullable|integer', 'milestone_date' => 'nullable|date',
                'tags' => 'nullable|array', 'tags.*' => 'required|string|max:100', 'notify' => 'nullable|in:true',
            ]);
            $report = $this->review->mutate($request->report, $request->user(), function (\App\Report $report) use ($data): void {
                foreach (['title', 'content', 'date', 'tags', 'previous_status', 'status', 'previous_progress', 'progress', 'measured_value'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $report->{$field} = $data[$field];
                    }
                }
                if ($report->type === 'milestone') {
                    $milestone = $report->goal->milestones()->findOrFail($data['milestone'] ?? $report->milestone_achieved);
                    $milestone->completed = $data['milestone_date'] ?? $data['date'];
                    $milestone->save();
                    $report->milestone()->associate($milestone);
                }
            });
            if (! $request->objective->hidden && $request->boolean('notify')) {
                Notification::send($request->objective->subscribers, new EditReport($request->objective, $request->goal, $report));
            }
        }
        return $this->redirectToReport($request)->with('success', 'Los cambios fueron guardados.');
    }

    public function viewReportComments(Request $request): \Illuminate\Contracts\View\View
    {
        return view('objective.manage.goals.reports.comments', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report, 'comments' => $request->report->comments()->paginate(10)]);
    }

    public function downloadReportComments(Request $request, CsvDownload $csvDownload): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->user()->hasRole('admin') || $request->user()->isManagerObjective($request->objective->id), 403);
        return $csvDownload->download(new ReportCommentsExport($request->report->id), now()->format('Ymd').'-comentarios-reporte-'.$request->report->id.'.csv');
    }

    public function viewReportTestimonies(Request $request): \Illuminate\Contracts\View\View
    {
        return view('objective.manage.goals.reports.testimonies', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report, 'testimonies' => $request->report->testimonies()->paginate(10)]);
    }

    public function downloadReportTestimonies(Request $request, CsvDownload $csvDownload): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->user()->hasRole('admin') || $request->user()->isManagerObjective($request->objective->id), 403);
        return $csvDownload->download(new ReportTestimoniesExport($request->report->id), now()->format('Ymd').'-me-gusta-reporte-'.$request->report->id.'.csv');
    }

    public function viewReportAlbum(Request $request): \Illuminate\Contracts\View\View
    {
        return view('objective.manage.goals.reports.album', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report, 'photos' => $request->report->photos()->paginate(10)]);
    }

    public function formReportAlbum(Request $request): RedirectResponse
    {
        $request->validate(['photo' => 'required|image|max:102400']);
        $this->review->addAttachments($request->report, $request->user(), $request);
        return back()->with('success', 'Se agregó la foto al reporte.');
    }

    public function formDeletePictureReport(Request $request): RedirectResponse
    {
        $request->validate(['review_version' => 'required|integer|min:1']);
        $this->review->mutate($request->report, $request->user(), function (\App\Report $report) use ($request): void {
            $this->review->checkVersion($report, $request->integer('review_version'));
            $photo = $report->photos()->findOrFail($request->route('pictureId'));
            $disk = $photo->disk ?? 'reports';
            $paths = $photo->disk ? [$photo->getRawOriginal('path'), $photo->getRawOriginal('thumbnail_path')] : ['photos/'.$photo->name, 'photos/'.$photo->thumbnail_name];
            $photo->delete();
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($disk, $paths): void {
                if (! Storage::disk($disk)->delete($paths)) {
                    throw new \RuntimeException('No se pudieron eliminar las imágenes del reporte.');
                }
            });
        }, 'photo-deleted');
        return back()->with('success', 'Se eliminó la foto.');
    }

    public function viewReportFiles(Request $request): \Illuminate\Contracts\View\View
    {
        return view('objective.manage.goals.reports.files', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report, 'files' => $request->report->files()->paginate(10)]);
    }

    public function formReportFile(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|max:102400']);
        $this->review->addAttachments($request->report, $request->user(), $request);
        return back()->with('success', 'Se agregó el archivo al reporte.');
    }

    public function viewReportMap(Request $request): \Illuminate\Contracts\View\View
    {
        return view('objective.manage.goals.reports.map', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report]);
    }

    public function formReportMap(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'map_lat' => 'nullable|numeric', 'map_long' => 'nullable|numeric', 'map_zoom' => 'nullable|numeric',
            'map_geometries' => 'nullable|json', 'map_center' => 'nullable|json', 'review_version' => 'required|integer|min:1',
        ]);
        $this->review->mutate($request->report, $request->user(), function (\App\Report $report) use ($request, $data): void {
            $this->review->checkVersion($report, $request->integer('review_version'));
            foreach (['map_lat', 'map_long', 'map_zoom', 'map_geometries', 'map_center'] as $field) {
                $report->{$field} = in_array($field, ['map_geometries', 'map_center'], true)
                    ? (isset($data[$field]) ? json_decode($data[$field], true, 512, JSON_THROW_ON_ERROR) : null)
                    : ($data[$field] ?? null);
            }
        }, 'map-edited');
        return back()->with('success', 'Mapa actualizado.');
    }

    public function viewReportConfiguration(Request $request): \Illuminate\Contracts\View\View
    {
        return view('objective.manage.goals.reports.configuration', ['objective' => $request->objective, 'goal' => $request->goal, 'report' => $request->report]);
    }

    public function formDeleteReport(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', new MatchOldPassword], 'notify' => 'nullable|in:true']);
        $report = $request->report;
        $this->review->delete($report, $request->user());
        if ($report->isApproved() && ! $request->objective->hidden && $request->boolean('notify')) {
            Notification::send($request->objective->subscribers, new DeleteReport($request->objective, $request->goal, $report));
        }
        return redirect()->route('objectives.manage.goals.reports', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id])->with('success', 'Reporte eliminado.');
    }

    private function redirectToReport(Request $request): RedirectResponse
    {
        return redirect()->route('objectives.manage.goals.reports.index', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id, 'reportId' => $request->report->id]);
    }
}
