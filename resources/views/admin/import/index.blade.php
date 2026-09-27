@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Importar estructura</h3>
  <p class="lead">Cargá o actualizá objetivos estratégicos, objetivos y metas desde una planilla.</p>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <h5 class="is-700 mt-4">Cómo funciona</h5>
  <ol>
    <li>Descargá la <b>planilla precargada</b> (lo que ya existe en la plataforma) o la <b>planilla de ejemplo</b>.</li>
    <li>Completala o editala en Excel, LibreOffice o Google Sheets. Una fila por meta.</li>
    <li>Guardala como CSV. En Excel: <i>Archivo › Guardar como › CSV UTF-8 (delimitado por comas)</i>. También se acepta "CSV" común y separado por punto y coma.</li>
    <li>Subila. Vas a ver una <b>vista previa</b> con lo que se va a crear o actualizar. <b>Nada se guarda hasta que confirmes.</b></li>
  </ol>

  <div class="my-3">
    <a href="{{ route('admin.import.template') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i><i class="fas fa-file-csv fa-fw"></i>Planilla precargada</a>
    <a href="{{ route('admin.import.example') }}" class="btn btn-link btn-sm"><i class="fas fa-download fa-fw"></i><i class="fas fa-file-csv fa-fw"></i>Planilla de ejemplo</a>
  </div>

  <div class="alert alert-light border">
    <p class="mb-1"><b>Para tener en cuenta</b></p>
    <ul class="mb-0 text-smaller">
      <li>Podés <b>combinar celdas verticalmente</b> en Eje, Código OE, Objetivo estratégico, Código objetivo, Objetivo y Descripción: las celdas vacías toman el valor de arriba.</li>
      <li><b>No combines celdas horizontalmente</b> en las filas de datos ni en las columnas de la meta. Arriba de los encabezados podés dejar títulos o agrupaciones: se ignoran.</li>
      <li>Los valores con opciones fijas (Estado, Tipo de medición, Dirección, etc.) aceptan mayúsculas, minúsculas y sin acentos.</li>
      <li>Los números pueden usar coma o punto decimal. No uses separador de miles.</li>
      <li>La importación <b>nunca borra</b> lo que no esté en el archivo y <b>no envía notificaciones</b>. Los objetivos nuevos se crean <b>ocultos</b>.</li>
      <li>Los ejes tienen que existir antes de importar.</li>
    </ul>
  </div>

  <form method="POST" action="{{ route('admin.import.upload') }}" enctype="multipart/form-data" class="my-4">
    @csrf
    <div class="form-group">
      <label><b>Archivo CSV</b></label>
      <input-file name="file" accept=".csv,text/csv"></input-file>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>&nbsp;Validar y ver vista previa</button>
  </form>

  <h5 class="is-700 mt-5">Columnas</h5>
  <div class="table-responsive">
    <table class="table table-sm table-bordered text-smaller">
      <thead class="thead-light">
        <tr>
          <th>Columna</th>
          <th>Obligatoria</th>
          <th>Valores</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($columns as $column)
        <tr>
          <td class="text-nowrap"><b>{{ $column['header'] }}</b></td>
          <td>{{ $column['required'] }}</td>
          <td>{{ $column['values'] }}</td>
          <td>{{ $column['description'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

@endsection
