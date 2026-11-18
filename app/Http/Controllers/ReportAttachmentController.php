<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportAttachmentController extends Controller
{
    public function show(Request $request, int $reportId, string $kind, int $attachmentId): StreamedResponse
    {
        $report = Report::findOrFail($reportId);
        abort_unless($report->isVisibleTo($request->user())
            || ($request->user() && $request->user()->hasVerifiedEmail() && $request->user()->can('viewInManagement', $report)), 404);
        $attachment = $kind === 'file'
            ? $report->files()->findOrFail($attachmentId)
            : $report->photos()->findOrFail($attachmentId);
        abort_unless($attachment->disk === 'reports_private', 404);
        $path = $attachment->getRawOriginal($kind === 'thumbnail' ? 'thumbnail_path' : 'path');
        abort_unless(Storage::disk('reports_private')->exists($path), 404);
        return Storage::disk('reports_private')->response($path, $attachment->name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $kind === 'file' ? 'attachment' : 'inline');
    }
}
