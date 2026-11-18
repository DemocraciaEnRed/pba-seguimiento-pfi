<?php

namespace App\Http\Controllers;

use App\Http\Requests\PfiMembershipRequest;
use App\Services\Pfi\PfiMemberships;
use App\Services\Reports\ReportContinuity;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PfiMembershipController extends Controller
{
    public function __construct(private readonly PfiMemberships $pfiMemberships)
    {
        $this->middleware('auth');
        $this->middleware('check_role:admin');
    }

    public function store(PfiMembershipRequest $request, string $userId): RedirectResponse
    {
        $member = User::findOrFail($userId);

        if (! $this->pfiMemberships->admit($member, $request->categoryIds(), $request->user())) {
            return $this->backToProfile($member)->with('warning', 'La persona ya era integrante PFI');
        }

        return $this->backToProfile($member)->with('success', 'La persona fue dada de alta como integrante PFI');
    }

    public function update(PfiMembershipRequest $request, string $userId): RedirectResponse
    {
        $member = User::with('pfiMembership')->findOrFail($userId);

        if (! $member->isPfiMember()) {
            return $this->backToProfile($member)->with('error', 'La persona no es integrante PFI');
        }

        $this->pfiMemberships->classify($member, $request->categoryIds(), $request->user());

        return $this->backToProfile($member)->with('success', 'Se actualizaron las categorías PFI');
    }

    private function backToProfile(User $member): RedirectResponse
    {
        return redirect()->route('admin.users.show', ['userId' => $member->id]);
    }

    public function confirmRemoval(string $userId, ReportContinuity $continuity): View
    {
        $member = User::findOrFail($userId);

        return view('admin.goal-assignments.continuity', [
            'member' => $member, 'isAdmin' => true, 'roleLabel' => 'integrante PFI',
            'reports' => $continuity->pendingForRemoval($member)
                ->with(['goal.reporters.pfiMembership', 'goal.objective'])->orderBy('goal_id')->orderBy('id')->get(),
            'coverage' => $member->responsibleGoals()->with('responsibles')->orderBy('title')->get(),
            'action' => route('admin.users.pfi.delete.form', ['userId' => $member->id]),
            'backUrl' => route('admin.users.show', ['userId' => $member->id]),
        ]);
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $member = User::findOrFail($userId);
        $data = $request->validate(['destinations' => ['sometimes', 'array'], 'destinations.*' => ['required', 'integer', 'min:1']]);
        $removed = $this->pfiMemberships->remove($member, $request->user(), array_map(intval(...), $data['destinations'] ?? []));

        return $this->backToProfile($member)->with($removed ? 'success' : 'warning', $removed
            ? 'Se retiró la pertenencia PFI y todas sus asignaciones. Los pendientes conservan su autoría y tienen continuidad.'
            : 'La persona ya no era integrante PFI.');
    }
}
