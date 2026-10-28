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

    public function approve(Request $request, int $reportId): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        return $this->transition($request, $reportId, 'approved');
    }

    public function returnReport(Request $request, int $reportId): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
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
            'approved' => 'Reporte aprobado y cambios aplicados.',
            'returned' => 'Reporte devuelto para corrección.',
            'resubmitted' => 'Reporte reenviado a revisión.',
        });
    }
}
