@use('App\Services\Workspace\WorkDesk')
@php
  $goal = $report->goal;
  $objective = $goal->objective;
  $category = $objective->strategicObjective?->category;
  $task = WorkDesk::taskType($report);
  $attendantName = $report->attendant?->fullname ?? $report->author?->fullname ?? 'usuario eliminado';
  $reportRoute = route('objectives.manage.goals.reports.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id]);
  $waitingDays = (int) $report->waiting_since?->diffInDays();
  $waitingLabel = match ($waitingDays) {
    0 => 'hoy',
    1 => 'hace 1 día',
    default => 'hace '.$waitingDays.' días',
  };
  [$nextStep, $actionLabel, $actionRoute] = match (true) {
    $context === 'action' && $task === WorkDesk::TASK_CORRECT => ['Corregilo y reenvialo a revisión.', 'Corregir', route('objectives.manage.goals.reports.edit', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])],
    $context === 'action' => ['Validalo y envialo a Administración, o devolvelo con un motivo.', 'Revisar', $reportRoute],
    $context === 'orphaned' => [$attendantName.' ya no puede atenderlo porque no está asignado a la meta. Reasigná la atención a un Reportero vigente.', 'Ver reporte', $reportRoute],
    $context === 'administration' && $task === WorkDesk::TASK_REVIEW => ['La meta no tiene Responsable: Administración puede validarlo.', 'Validar', $reportRoute],
    $context === 'administration' && $task === WorkDesk::TASK_PUBLISH => ['Validado. Publicalo para aplicar sus cambios en el portal.', 'Publicar', $reportRoute],
    $context === 'administration' => ['Validado. Cerralo y consolidalo para el seguimiento interno.', 'Cerrar', $reportRoute],
    $task === WorkDesk::TASK_CORRECT => ['Espera la corrección de '.$attendantName.'.', 'Ver reporte', $reportRoute],
    $task === WorkDesk::TASK_PUBLISH => ['Espera que Administración lo publique.', 'Ver reporte', $reportRoute],
    $task === WorkDesk::TASK_CLOSE => ['Espera que Administración lo cierre y consolide.', 'Ver reporte', $reportRoute],
    $goal->responsibles_count > 0 => ['Espera la revisión de un Responsable.', 'Ver reporte', $reportRoute],
    default => ['Espera la revisión de Administración porque la meta no tiene Responsable.', 'Ver reporte', $reportRoute],
  };
@endphp
<div class="list-group-item d-flex align-items-start gap-3">
  <i class="{{ $report->type_icon }} fa-fw text-muted mt-1" title="{{ $report->type_label }}"></i>
  <div class="flex-grow-1 min-w-0">
    <p class="mb-0 text-smaller text-muted">
      <span class="badge badge-primary">{{ WorkDesk::taskOptions()[$task] }}</span>
      <span class="badge badge-secondary">{{ $report->review_status_label }}</span>
      @if($goal->visibility === 'internal')
      <span class="badge badge-dark">Interna</span>
      @endif
      · Desde el {{ $report->waiting_since?->format('d/m/Y') ?? '-' }} ({{ $waitingLabel }})
    </p>
    <a href="{{ $reportRoute }}" class="is-600 text-dark">{{ $report->title }}</a>
    <p class="mb-0 text-smaller">Meta: {{ $goal->title }}</p>
    <p class="mb-0 text-smaller text-muted text-truncate" title="{{ $category ? $category->title.' · ' : '' }}{{ $objective->title }}">
      @if($category)
      <span style="color: {{ $category->color }}">{{ $category->title }}</span> ·
      @endif
      {{ $objective->title }}
    </p>
    <p class="mb-0 text-smaller">{{ $nextStep }}</p>
    @if($context === 'action' && $task === WorkDesk::TASK_CORRECT && $report->return_reason)
    <p class="mb-0 text-smaller text-muted">Motivo de la devolución: {{ Str::limit($report->return_reason, 160) }}</p>
    @endif
    <p class="mb-0 text-smaller text-muted">Por {{ $report->author?->fullname ?? 'usuario eliminado' }}</p>
  </div>
  <a href="{{ $actionRoute }}" @class(['btn btn-sm text-nowrap', 'btn-primary' => $context !== 'waiting', 'btn-outline-secondary' => $context === 'waiting'])>{{ $actionLabel }}</a>
</div>
