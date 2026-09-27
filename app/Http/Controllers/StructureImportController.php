<?php

namespace App\Http\Controllers;

use App\Exports\StructureImportExampleExport;
use App\Exports\StructureImportTemplateExport;
use App\Imports\Structure\ImportAction;
use App\Imports\Structure\StructureImportColumns;
use App\Imports\Structure\StructureImporter;
use App\Imports\Structure\StructureImportPlan;
use App\Imports\Structure\StructureImportPlanner;
use App\Services\Csv\CsvDownload;
use App\Services\Csv\CsvReader;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StructureImportController extends Controller
{
    /**
     * The uploaded file lives on the private local disk; only its path is kept in the admin's session.
     */
    private const string SESSION_KEY = 'structure_import_path';

    public function __construct(private StructureImportPlanner $planner, private CsvReader $csvReader)
    {
        $this->middleware('auth');
        $this->middleware('check_role:admin');
    }

    public function index(): View
    {
        return view('admin.import.index', ['columns' => StructureImportColumns::documentation()]);
    }

    public function downloadExample(CsvDownload $csvDownload): StreamedResponse
    {
        return $csvDownload->download(new StructureImportExampleExport(), 'ejemplo-importacion-estructura.csv');
    }

    public function downloadTemplate(CsvDownload $csvDownload): StreamedResponse
    {
        return $csvDownload->download(new StructureImportTemplateExport(), Carbon::now()->format('Ymd').'-estructura.csv');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], ['file' => 'archivo']);

        $this->discardUpload($request);
        $path = Storage::disk('local')->putFileAs('imports', $request->file('file'), Str::uuid().'.csv');
        $request->session()->put(self::SESSION_KEY, $path);

        return redirect()->route('admin.import.preview');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $plan = $this->planFromSession($request);

        if ($plan instanceof RedirectResponse) {
            return $plan;
        }

        return view('admin.import.preview', ['plan' => $plan]);
    }

    public function confirm(Request $request, StructureImporter $importer): RedirectResponse
    {
        $plan = $this->planFromSession($request);

        if ($plan instanceof RedirectResponse) {
            return $plan;
        }

        if ($plan->hasErrors()) {
            return redirect()->route('admin.import.preview')->with('warning', 'El archivo tiene errores: corregilos y volvé a subirlo.');
        }

        $importer->import($plan, $request->user());
        $this->discardUpload($request);

        $summary = collect($plan->summary())
            ->map(fn (array $counts, string $entity): string => "{$entity}: {$counts[ImportAction::Create->value]} creados, {$counts[ImportAction::Update->value]} actualizados")
            ->implode('. ');

        return redirect()->route('admin.import')->with('success', "Importación completada. {$summary}.");
    }

    private function planFromSession(Request $request): StructureImportPlan|RedirectResponse
    {
        $path = $request->session()->get(self::SESSION_KEY);

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('admin.import')->with('warning', 'Subí un archivo para ver la vista previa.');
        }

        try {
            $rows = $this->csvReader->read(Storage::disk('local')->path($path), StructureImportColumns::required());
        } catch (ValidationException $exception) {
            $this->discardUpload($request);

            return redirect()->route('admin.import')->withErrors($exception->errors());
        }

        return $this->planner->plan($rows);
    }

    private function discardUpload(Request $request): void
    {
        $path = $request->session()->pull(self::SESSION_KEY);

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
