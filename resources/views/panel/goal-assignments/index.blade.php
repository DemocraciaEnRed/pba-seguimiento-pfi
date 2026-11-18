@extends('panel.master')

@section('panelContent')

<section>
  <h3 class="is-700">Reporteros de mis metas</h3>
  <p class="lead">Podés incorporar o quitar Reporteros únicamente en las metas que tenés asignadas como Responsable.</p>
  <div class="list-group shadow-sm">
    @forelse($goals as $goal)
    <a href="{{ route('panel.goal-assignments.goal', ['goalId' => $goal->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3">
      <span class="min-w-0">
        <span class="d-block text-smaller text-muted">{{ $goal->objective->title }}</span>
        <span class="d-block is-700 text-dark">{{ $goal->title }}</span>
      </span>
      <i class="fas fa-arrow-right fa-fw text-muted"></i>
    </a>
    @empty
    <div class="list-group-item text-center text-muted">No tenés metas asignadas como Responsable.</div>
    @endforelse
  </div>
  {{ $goals->links() }}
</section>

@endsection
