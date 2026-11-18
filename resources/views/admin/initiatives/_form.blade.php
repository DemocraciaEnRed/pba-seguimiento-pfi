@php
  $selectedCategories = collect(old('categories', $initiative->exists ? $initiative->categories->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp
@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
<div class="form-group">
  <label for="initiative-title"><b>Título</b> <small class="text-danger">*</small></label>
  <input type="text" id="initiative-title" class="form-control" name="title" value="{{ old('title', $initiative->title) }}" maxlength="255" required>
  <small class="text-muted">Nombre de la iniciativa, sistema o herramienta. Hasta 255 caracteres.</small>
</div>
<div class="form-group">
  <label for="initiative-subtitle"><b>Subtítulo</b> <small class="text-danger">*</small></label>
  <textarea id="initiative-subtitle" name="subtitle" class="form-control" rows="3" maxlength="600" required>{{ old('subtitle', $initiative->subtitle) }}</textarea>
  <small class="text-muted">Hasta 600 caracteres. La tarjeta muestra hasta 3 líneas; el texto completo se lee al abrir «Ver detalle».</small>
</div>
<div class="form-group">
  <label><b>Ejes vinculados</b> <small class="text-danger">*</small></label>
  <div class="row">
    @foreach($categories as $category)
    <div class="col-md-6">
      <div class="custom-control custom-checkbox mb-1">
        <input type="checkbox" class="custom-control-input" id="initiative-category-{{ $category->id }}" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories, true))>
        <label class="custom-control-label" for="initiative-category-{{ $category->id }}">
          <span class="d-inline-block rounded-circle align-middle mr-1" style="width:.75rem;height:.75rem;background-color:{{ $category->color }}"></span>{{ $category->title }}
        </label>
      </div>
    </div>
    @endforeach
  </div>
  <small class="text-muted">La iniciativa se mostrará en la sección de cada eje seleccionado en «El Plan».</small>
</div>
<div class="form-group">
  <h4 class="h6"><b>Enlaces externos</b> <small class="text-info">Opcional</small></h4>
  @php
    $links = old('links', $initiative->links ?? []);
  @endphp
  <input-initiative-links :links="{{ \Illuminate\Support\Js::from(is_array($links) ? array_values($links) : []) }}"></input-initiative-links>
  <small class="text-muted">Se muestran en el detalle, en este orden, y abren una pestaña nueva. Cada enlace debe tener etiqueta y URL completa (http:// o https://).</small>
</div>
<div class="form-row">
  <div class="form-group col-md-4">
    <label for="initiative-order"><b>Orden</b></label>
    <input type="number" id="initiative-order" class="form-control" name="order" value="{{ old('order', $initiative->order ?? 0) }}" min="0" max="9999">
    <small class="text-muted">Las de menor número se muestran primero.</small>
  </div>
  <div class="form-group col-md-8 d-flex align-items-center">
    <div class="custom-control custom-switch mt-md-3">
      <input type="checkbox" class="custom-control-input" id="initiative-featured" name="featured_on_home" value="1" @checked(old('featured_on_home', $initiative->featured_on_home))>
      <label class="custom-control-label" for="initiative-featured"><b>Destacar en la página de inicio</b></label>
    </div>
  </div>
</div>
<div class="form-group">
  <label><b>Imagen de fondo</b> <small class="text-info">Opcional</small></label>
  @if($initiative->background_path)
  <div class="d-flex align-items-center mb-2">
    <img src="{{ $initiative->backgroundUrl() }}" alt="" class="rounded mr-3 object-cover" width="160" height="90">
    <div class="custom-control custom-checkbox">
      <input type="checkbox" class="custom-control-input" id="initiative-remove-background" name="remove_background" value="1">
      <label class="custom-control-label" for="initiative-remove-background">Quitar imagen de fondo</label>
    </div>
  </div>
  @endif
  <input-file name="background" accept="image/jpeg,image/png,image/webp"></input-file>
  <small class="text-muted">JPG, PNG o WEBP de hasta 5MB. Se recomienda una foto horizontal de al menos 1200px de ancho. Se le aplica automáticamente una capa con el color del eje para asegurar la legibilidad del texto.</small>
</div>
<div class="form-group">
  <label><b>Logo</b> <small class="text-info">Opcional</small></label>
  @if($initiative->logo_path)
  <div class="d-flex align-items-center mb-2">
    <img src="{{ $initiative->logoUrl() }}" alt="" class="rounded border bg-white p-1 mr-3 object-contain" width="90" height="90">
    <div class="custom-control custom-checkbox">
      <input type="checkbox" class="custom-control-input" id="initiative-remove-logo" name="remove_logo" value="1">
      <label class="custom-control-label" for="initiative-remove-logo">Quitar logo</label>
    </div>
  </div>
  @endif
  <input-file name="logo" accept="image/jpeg,image/png"></input-file>
  <small class="text-muted">PNG o JPG de hasta 2MB. Se muestra dentro de un recuadro blanco, por lo que no es necesario que tenga fondo transparente.</small>
</div>
