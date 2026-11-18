@extends('admin.master')

@section('adminContent')

<section>
<h3 class="is-700">Editar organización</h3>
 <p class="lead">Para editar los datos de la organización, completá los siguientes campos. Una organización no se elimina: se desactiva.</p>
  @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('admin.organizations.edit.form',['organizationId' => $organization->id]) }}" enctype="multipart/form-data">
    @method('PUT')
    @csrf
    <div class="form-group">
      <label><b>Nombre</b> <small class="text-danger">*</small></label>
      <input type="text" class="form-control" name="name" placeholder="Ingrese un nombre" value="{{$organization->name}}" maxlength="255">
      <small class="text-muted">Hasta 225 caracteres.</small>
    </div>
    <div class="form-group">
      <label><b>Descripción</b> <small class="text-danger">*</small></label>
      <textarea name="description" class="form-control" rows="4" maxlength="550">{{$organization->description}}</textarea>
      <small class="text-muted">Hasta 550 caracteres.</small>
    </div>
    <div class="form-group">
      <label><b>Logo</b> <small class="text-info">Opcional</small></label>
      <input-file name="logo" accept="image/*"></input-file>
      <small class="text-muted">Se recomienda que no exceda 2MB y que sea PNG con fondo transparente.</small>
    </div>
    <div class="form-group">
      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="organization-active" name="is_active" value="1" @checked($errors->any() ? old('is_active') : $organization->is_active)>
        <label class="custom-control-label" for="organization-active"><b>Activa</b></label>
      </div>
      <small class="text-muted">Las organizaciones inactivas no se ofrecen para nuevas asociaciones. Las metas que ya la tienen la conservan y los reportes publicados mantienen su nombre.</small>
    </div>
    <button type="submit" class="btn btn-primary">Editar</button>
  </form>

</section>

@endsection