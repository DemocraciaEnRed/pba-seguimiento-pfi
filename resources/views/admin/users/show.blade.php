@extends('admin.master')

@section('adminContent')

<section>
  @include('admin.users.partials.header')

  <div class="d-grid grid-cols-2 grid-cols-md-3 grid-cols-lg-5 gap-3 mb-4">
    @foreach([
      ['label' => 'Coordina', 'icon' => 'fas fa-flag-checkered', 'value' => $counts['managed'], 'route' => route('admin.users.objectives', ['userId' => $user->id])],
      ['label' => 'Reporta', 'icon' => 'fas fa-marker', 'value' => $counts['reported'], 'route' => route('admin.users.objectives', ['userId' => $user->id])],
      ['label' => 'Suscripciones', 'icon' => 'fas fa-envelope', 'value' => $counts['subscriptions'], 'route' => route('admin.users.objectives', ['userId' => $user->id])],
      ['label' => 'Comentarios', 'icon' => 'far fa-comments', 'value' => $counts['comments']],
      ['label' => 'Reportes', 'icon' => 'far fa-file-lines', 'value' => $counts['reports']],
    ] as $item)
      @include('admin.partials.stat', $item)
    @endforeach
  </div>

  <div class="d-grid grid-cols-1 grid-cols-lg-3 gap-4">
    <div class="col-span-lg-2">
      <h5 class="is-700 mb-3"><i class="fas fa-user-pen fa-fw"></i>&nbsp;Datos del usuario</h5>
      <form method="POST" action="{{ route('admin.users.update.form', ['userId' => $user->id]) }}">
        @csrf
        @method('PUT')
        <div class="d-grid grid-cols-1 grid-cols-md-2 column-gap-3">
          <div class="form-group">
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" @class(['form-control', 'is-invalid' => $errors->has('name')]) required maxlength="255">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-group">
            <label for="surname">Apellido</label>
            <input type="text" id="surname" name="surname" value="{{ old('surname', $user->surname) }}" @class(['form-control', 'is-invalid' => $errors->has('surname')]) required maxlength="255">
            @error('surname')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" @class(['form-control', 'is-invalid' => $errors->has('email')]) required maxlength="255">
          @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          <small class="form-text text-muted">Si cambiás el email, la cuenta conserva su estado de verificación.</small>
        </div>
        <div class="form-group">
          <label for="organization">Organización</label>
          <input type="text" id="organization" name="organization" value="{{ old('organization', $user->organization) }}" @class(['form-control', 'is-invalid' => $errors->has('organization')]) maxlength="550">
          @error('organization')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="mailable" name="mailable" value="true" @checked(old('mailable', str_contains((string) $user->notification_preferences, 'mail') ? 'true' : null) === 'true')>
          <label class="custom-control-label" for="mailable">Recibe notificaciones por email</label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk fa-fw"></i>Guardar cambios</button>
      </form>
    </div>

    <div>
      <h5 class="is-700 mb-3"><i class="fas fa-user-gear fa-fw"></i>&nbsp;Cuenta</h5>
      <div class="d-flex flex-column gap-2">
        @unless($user->hasVerifiedEmail())
        <form method="POST" action="{{ route('admin.users.verify.form', ['userId' => $user->id]) }}">
          @csrf
          <button type="submit" class="btn btn-outline-success btn-block btn-sm"><i class="fas fa-user-check fa-fw"></i>Marcar como verificado</button>
        </form>
        <form method="POST" action="{{ route('admin.users.verification.form', ['userId' => $user->id]) }}">
          @csrf
          <button type="submit" class="btn btn-outline-primary btn-block btn-sm"><i class="fas fa-envelope fa-fw"></i>Reenviar email de verificación</button>
        </form>
        @endunless
        <form method="POST" action="{{ route('admin.users.password.form', ['userId' => $user->id]) }}">
          @csrf
          <button type="submit" class="btn btn-outline-primary btn-block btn-sm"><i class="fas fa-key fa-fw"></i>Enviar link para restablecer contraseña</button>
        </form>
        @if($user->roles->contains('name', 'admin'))
          @if($user->is(Auth::user()))
          <p class="text-smaller text-muted mb-0"><i class="fas fa-circle-info fa-fw"></i>No podés quitarte el rol de administrador a vos mismo.</p>
          @else
          <form method="POST" action="{{ route('admin.users.admin.revoke.form', ['userId' => $user->id]) }}" onsubmit="return confirm('¿Quitar el rol de administrador a {{ $user->fullname }}?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-block btn-sm"><i class="fas fa-user-shield fa-fw"></i>Quitar rol de administrador</button>
          </form>
          @endif
        @else
        <form method="POST" action="{{ route('admin.users.admin.grant.form', ['userId' => $user->id]) }}" onsubmit="return confirm('¿Dar el rol de administrador a {{ $user->fullname }}? Podrá gestionar toda la plataforma.')">
          @csrf
          <button type="submit" class="btn btn-outline-dark btn-block btn-sm"><i class="fas fa-user-shield fa-fw"></i>Hacer administrador</button>
        </form>
        @endif
      </div>
      <p class="text-smaller text-muted mt-3 mb-0">
        Alta: @datetime($user->created_at)<br>
        @if($user->hasVerifiedEmail())Verificado: @datetime($user->email_verified_at)@endif
      </p>
    </div>
  </div>

  <hr class="my-4">

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="is-700 mb-0"><i class="fas fa-list-check fa-fw"></i>&nbsp;Últimas acciones</h5>
    <a href="{{ route('admin.logs') }}" class="text-smaller">Ver bitácora<i class="fas fa-arrow-right fa-fw"></i></a>
  </div>
  @forelse($latestActions as $log)
  <div class="text-smaller">
    <p class="mb-1"><b>@datetime($log->record_datetime)</b> - {{ $log->message }}</p>
  </div>
  @empty
  <p class="text-muted text-smaller">El usuario no tiene acciones registradas.</p>
  @endforelse
</section>

@endsection
