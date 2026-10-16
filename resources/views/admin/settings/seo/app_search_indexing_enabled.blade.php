<form method="POST" action="{{ route('admin.settings.form') }}">
  @method('PUT')
  @csrf
  <label><b>Indexación en buscadores</b></label>
  <p class="text-muted text-smaller">
    Permita que los buscadores indexen las páginas públicas de la plataforma. La administración, los paneles de gestión y el contenido oculto permanecerán excluidos.
  </p>
  <p class="text-muted text-smaller">
    Al desactivarlo, se solicitará a los buscadores que no incluyan la plataforma en sus resultados. Esto no restringe el acceso público ni garantiza que todos los bots respeten la indicación. Los cambios en los buscadores pueden demorar.
  </p>
  <div class="form-group">
    <div class="custom-control custom-switch">
      <input type="hidden" name="name" value="app_search_indexing_enabled">
      <input type="hidden" name="type" value="boolean">
      <input type="hidden" name="cached" value="true">
      <input id="app_search_indexing_enabled" type="checkbox" class="custom-control-input" name="value" value="true" {{ app_setting('app_search_indexing_enabled', false) ? 'checked' : '' }}>
      <label for="app_search_indexing_enabled" class="custom-control-label">Permitir que los buscadores indexen las páginas públicas</label>
    </div>
  </div>
  <button type="submit" class="btn btn-sm btn-primary">Editar</button>
</form>
