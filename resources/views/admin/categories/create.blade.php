@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Crear eje</h3>
  <p class="lead">Para crear un nuevo eje, completá los campos a continuación:</p>
  @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('admin.categories.create.form') }}">
    @csrf
    <div class="form-group">
      <label><b>Título</b></label>
      <input type="text" class="form-control" name="title" placeholder="Ingrese aquí" maxlength="255" >
      <small class="form-text text-muted">Hasta 225 caracteres</small>
    </div>
    <div class="form-group">
      <label><b>Ícono</b></label>
      <input-icon name="icon"></input-icon>
    </div>
    <div class="form-group">
      <label><b>Color del ícono</b></label>
      <input type="color" class="form-control" name="color">
    </div>
    <div class="form-group">
      <label><b>N° de eje</b></label>
      <input type="number" class="form-control" name="order" placeholder="Ingrese aquí" min="0" value="0">
      <small class="form-text text-muted">Define el orden en que se muestra el eje respecto a los demás.</small>
    </div>
    <div class="form-group">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="mb-0"><b>Objetivos Estratégicos</b></label>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-add-strategic-objective><i class="fas fa-plus"></i> Agregar</button>
      </div>
      <div data-strategic-objectives-list></div>
      <small class="form-text text-muted">Podés crear los objetivos estratégicos del eje ahora o agregarlos más adelante.</small>
    </div>
    <div class="text-right">
    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Crear</button>
    </div>
  </form>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('[data-strategic-objectives-list]');
    const addButton = document.querySelector('[data-add-strategic-objective]');
    let rowIndex = 0;

    addButton.addEventListener('click', function () {
      const row = document.createElement('div');
      row.className = 'border border-light rounded p-3 mb-2';
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
