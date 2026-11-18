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
  <label for="pfi-category-name"><b>Nombre</b> <small class="text-danger">*</small></label>
  <input type="text" id="pfi-category-name" @class(['form-control', 'is-invalid' => $errors->has('name')]) name="name" value="{{ old('name', $category->name) }}" maxlength="120" required>
  <small class="text-muted">Hasta 120 caracteres. No puede repetirse.</small>
</div>
<div class="form-group">
  <div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="pfi-category-active" name="is_active" value="1" @checked(old('is_active', $category->is_active))>
    <label class="custom-control-label" for="pfi-category-active"><b>Activa</b></label>
  </div>
  <small class="text-muted">Las categorías inactivas no se pueden elegir al dar de alta o clasificar integrantes. Quienes ya la tienen la conservan.</small>
</div>
