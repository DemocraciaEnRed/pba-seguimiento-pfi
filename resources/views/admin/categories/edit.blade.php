@extends('admin.master')

@section('adminContent')

<section>
<h3 class="is-700">Editar eje</h3>
  <p class="lead">Para editar el eje, completá los campos a continuación:</p>
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('admin.categories.edit.form', ['categoryId' => $category->id]) }}">
    @method('PUT')
    @csrf
    <div class="form-group">
      <label><b>Título</b></label>
      <input type="text" class="form-control" name="title" placeholder="Ingrese aquí" maxlength="255" value="{{$category->title}}">
      <small class="form-text text-muted">Hasta 225 caracteres</small>
    </div>
    <div class="form-group">
      <label><b>Ícono</b></label>
      <input-icon name="icon" value="{{$category->icon}}"></input-icon>
    </div>
    <div class="form-group">
      <label><b>Color del ícono</b></label>
      <input type="color" class="form-control" name="color" value="{{$category->color}}">
    </div>
    <div class="form-group">
      <label><b>N° de eje</b></label>
      <input type="number" class="form-control" name="order" placeholder="Ingrese aquí" min="0" value="{{$category->order}}">
      <small class="form-text text-muted">Define el orden en que se muestra el eje respecto a los demás.</small>
    </div>
    <div class="form-group">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="mb-0"><b>Objetivos Estratégicos</b></label>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-add-strategic-objective><i class="fas fa-plus"></i> Agregar</button>
      </div>
      <div data-strategic-objectives-list>
        @foreach ($category->strategicObjectives as $index => $strategicObjective)
          <div class="border border-light rounded p-3 mb-2" data-strategic-objective-row>
            <input type="hidden" name="strategic_objectives[{{$index}}][id]" value="{{$strategicObjective->id}}">
            <input type="hidden" name="strategic_objectives[{{$index}}][delete]" value="0" data-delete-value>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Código</label>
                <input type="text" class="form-control" name="strategic_objectives[{{$index}}][codigo]" maxlength="225" value="{{$strategicObjective->codigo}}" required>
              </div>
              <div class="form-group col-md-7">
                <label>Título</label>
                <input type="text" class="form-control" name="strategic_objectives[{{$index}}][title]" maxlength="550" value="{{$strategicObjective->title}}" required>
              </div>
              <div class="form-group col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger mb-3" data-remove-strategic-objective title="Quitar"><i class="fas fa-trash"></i></button>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <small class="form-text text-muted">Los objetivos estratégicos con objetivos específicos asociados no pueden eliminarse.</small>
    </div>
    <button type="submit" class="btn btn-primary">Editar</button>
  </form>

</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('[data-strategic-objectives-list]');
    const addButton = document.querySelector('[data-add-strategic-objective]');
    let rowIndex = {{$category->strategicObjectives->count()}};

    list.querySelectorAll('[data-remove-strategic-objective]').forEach(function (button) {
      button.addEventListener('click', function () {
        const row = button.closest('[data-strategic-objective-row]');
        const deleteValue = row.querySelector('[data-delete-value]');
        if (deleteValue) {
          deleteValue.value = '1';
          row.classList.add('d-none');
          row.querySelectorAll('input:not([data-delete-value])').forEach(function (input) {
            input.required = false;
          });
        } else {
          row.remove();
        }
      });
    });

    addButton.addEventListener('click', function () {
      const row = document.createElement('div');
      row.className = 'border border-light rounded p-3 mb-2';
      row.setAttribute('data-strategic-objective-row', '');
      row.innerHTML = `
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Código</label>
            <input type="text" class="form-control" name="strategic_objectives[${rowIndex}][codigo]" maxlength="225" required>
          </div>
          <div class="form-group col-md-7">
            <label>Título</label>
            <input type="text" class="form-control" name="strategic_objectives[${rowIndex}][title]" maxlength="550" required>
          </div>
          <div class="form-group col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-outline-danger mb-3" data-remove-strategic-objective title="Quitar"><i class="fas fa-trash"></i></button>
          </div>
        </div>`;
      row.querySelector('[data-remove-strategic-objective]').addEventListener('click', function () {
        row.remove();
      });
      list.appendChild(row);
      rowIndex++;
    });
  });
</script>

@endsection
