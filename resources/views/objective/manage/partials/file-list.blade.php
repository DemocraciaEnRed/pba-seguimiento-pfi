{{-- @param \Illuminate\Pagination\LengthAwarePaginator $files --}}
<div class="d-flex justify-content-between align-items-center mb-2">
  <span class="text-smaller text-muted">{{ $files->total() }} archivo(s)</span>
</div>
<div class="list-group shadow-sm mb-3">
  @forelse($files as $file)
  @php
    $mime = (string) $file->mime;
    $fileIcon = match (true) {
      str_contains($mime, 'pdf') => 'fa-file-pdf',
      str_starts_with($mime, 'image/') => 'fa-file-image',
      str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_contains($mime, 'csv') => 'fa-file-excel',
      str_contains($mime, 'word') || str_contains($mime, 'document') => 'fa-file-word',
      str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint') => 'fa-file-powerpoint',
      str_contains($mime, 'zip') || str_contains($mime, 'compressed') => 'fa-file-zipper',
      default => 'fa-file',
    };
  @endphp
  <div class="list-group-item d-flex align-items-center gap-3">
    <i class="far {{ $fileIcon }} fa-fw fa-lg text-muted"></i>
    <div class="flex-grow-1 min-w-0">
      <span class="d-block text-truncate" title="{{ $file->name }}">{{ $file->name }}</span>
      <span class="text-smaller text-muted">{{ $mime }}</span>
    </div>
    <a href="{{ $file->disk === 'reports_private' ? $file->path : asset($file->path) }}" class="btn btn-outline-primary btn-sm text-nowrap" download><i class="fas fa-download fa-fw"></i>Descargar</a>
  </div>
  @empty
  <div class="list-group-item text-center text-muted py-4">No hay archivos cargados</div>
  @endforelse
</div>
{{ $files->links() }}
