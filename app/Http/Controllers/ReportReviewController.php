<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
use App\Services\Reports\ReportReview;
use Illuminate\Http\RedirectResponse;

class ReportReviewController extends Controller
{
    public function __construct(private ReportReview $review)
    {
        $this->middleware(['auth', 'verified']);
    }

    public function validateReport(Request $request, int $reportId): RedirectResponse
    {
        return $this->transition($request, $reportId, 'validated');
    }

    public function publish(Request $request, int $reportId): RedirectResponse
    {
        return $this->transition($request, $reportId, 'published');
    }

    public function close(Request $request, int $reportId): RedirectResponse
    {
        return $this->transition($request, $reportId, 'closed');
    }

    public function returnReport(Request $request, int $reportId): RedirectResponse
    {
        return $this->transition($request, $reportId, 'returned');
    }

    public function resubmit(Request $request, int $reportId): RedirectResponse
    {
        return $this->transition($request, $reportId, 'resubmitted');
    }

    private function transition(Request $request, int $reportId, string $action): RedirectResponse
    {
        $report = Report::findOrFail($reportId);
        $report = $this->review->transition($report, $request->user(), $request, $action);
        return redirect()->route('objectives.manage.goals.reports.index', [
            'objectiveId' => $report->goal->objective_id, 'goalId' => $report->goal_id, 'reportId' => $report->id,
        ])->with('success', match ($action) {
            'validated' => 'Reporte validado; no se publicó ni se aplicaron sus cambios.',
            'published' => 'Reporte publicado y cambios aplicados.',
            'closed' => 'Reporte interno cerrado y consolidado para el seguimiento.',
            'returned' => 'Reporte devuelto para corrección.',
            'resubmitted' => 'Reporte reenviado a revisión.',
        });
    }
}
