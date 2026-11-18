<?php

namespace App\Http\Controllers;

use App\Services\Workspace\WorkDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkDeskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('verified');
    }

    public function index(Request $request, WorkDesk $workDesk): View
    {
        Gate::authorize('accessWorkDesk');
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        $filters = $workDesk->normalize($request->query());

        return view('panel.desk.index', [
            'filters' => $filters,
            'isAdmin' => $isAdmin,
            'summary' => $workDesk->summary($user),
            'actionRequired' => $workDesk->actionRequired($user, $filters)->paginate(10, pageName: 'pagina_accion')->withQueryString()->fragment('accion'),
            'waiting' => $workDesk->waiting($user, $filters)->paginate(10, pageName: 'pagina_esperando')->withQueryString()->fragment('esperando'),
            'administrationTasks' => $isAdmin ? $workDesk->administrationTasks($filters)->paginate(10, pageName: 'pagina_administracion')->withQueryString()->fragment('administracion') : null,
            'uncoveredGoals' => $isAdmin ? $workDesk->uncoveredGoals($filters)->paginate(10, pageName: 'pagina_cobertura')->withQueryString()->fragment('alertas') : null,
            'orphanedReports' => $isAdmin ? $workDesk->orphanedReports($filters)->paginate(10, pageName: 'pagina_sin_atencion')->withQueryString()->fragment('alertas') : null,
            'goals' => $workDesk->goals($user, $filters)->paginate(10, pageName: 'pagina_metas')->withQueryString()->fragment('metas'),
            ...$workDesk->hierarchyOptions($user, $filters),
        ]);
    }
}
