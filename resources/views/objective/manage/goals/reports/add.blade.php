@extends('objective.manage.goals.master')

@section('panelContent')

<section>
  <h3 class="is-700">Nuevo reporte</h3>
  <p class="lead">Podés hacer reportes sobre la meta. Comenzá eligiendo el tipo de reporte, recordá que hay 3 tipos de reportes.</p>
  <hr>
  <p class="alert alert-info">
    @if(auth()->user()->hasRole('admin') && $goal->visibility === 'public')
      La carga de Administración publica y aplica el reporte directamente.
    @elseif(auth()->user()->hasRole('admin'))
      El reporte interno quedará validado. Administración deberá cerrarlo y consolidarlo; no se publicará.
    @else
      El reporte quedará pendiente de revisión. Un Responsable puede devolverlo o validarlo; validar no publica ni aplica cambios.
    @endif
  </p>
  @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif


  <form-new-report form-url="{{ route('objectives.manage.goals.reports.add.form',['objectiveId' => $objective->id, 'goalId' => $goal->id])}}" crsf-token="{{ csrf_token() }}" :direct-publish='@json(auth()->user()->hasRole("admin") && $goal->visibility === "public")' :is-admin='@json(auth()->user()->hasRole("admin"))' :goal='@json($goal)' :objective='@json($objective)' :milestones='@json($goal->milestones)' :periods='@json($periods)'>
    <div class="alert alert-light text-center">
      <p class="m-0"><i class="fas fa-arrows-rotate fa-spin"></i>&nbsp;Cargando</p>
      <span class="text-smaller font-italic">Si no carga, intente recargar la pagina</span>
    </div>
  </form-new-report>
</section>

@endsection
