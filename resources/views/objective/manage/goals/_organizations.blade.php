@php
  $hasOldInput = $errors->any();
  $selectedResponsible = (string) ($hasOldInput ? old('responsible_organization') : $goal?->responsible_organization_id);
  $selectedParticipants = array_map('strval', $hasOldInput ? (array) old('participant_organizations', []) : ($goal?->participantOrganizations->modelKeys() ?? []));
@endphp
<fieldset class="form-group">
  <legend class="col-form-label pt-0"><b>Organizaciones</b> <small class="text-info">Opcional</small></legend>
  @if($organizationOptions->isEmpty())
  <p class="text-smaller text-muted mb-0">No hay organizaciones activas. Podés cargarlas en <a href="{{ route('admin.organizations') }}">Organizaciones</a>.</p>
  @else
  <label for="responsible-organization">Organización responsable</label>
  <select id="responsible-organization" name="responsible_organization" @class(['custom-select', 'is-invalid' => $errors->has('responsible_organization')])>
    <option value="">Sin organización responsable</option>
    @foreach($organizationOptions as $organization)
    <option value="{{ $organization->id }}" @selected($selectedResponsible === (string) $organization->id)>{{ $organization->name }}@unless($organization->is_active) (inactiva)@endunless</option>
    @endforeach
  </select>
  @error('responsible_organization')<div class="invalid-feedback">{{ $message }}</div>@enderror
  <small class="form-text text-muted">Es la organización a cargo del reporte de la meta. Se muestra como dato principal en la ficha pública.</small>
  <p class="mt-3 mb-1">Organizaciones participantes</p>
  <div>
    @foreach($organizationOptions as $organization)
    <div class="custom-control custom-checkbox form-check-inline">
      <input class="custom-control-input" type="checkbox" name="participant_organizations[]" id="participant-organization-{{ $organization->id }}" value="{{ $organization->id }}" @checked(in_array((string) $organization->id, $selectedParticipants, true))>
      <label class="custom-control-label" for="participant-organization-{{ $organization->id }}">{{ $organization->name }}@unless($organization->is_active) <small class="text-muted">(inactiva)</small>@endunless</label>
    </div>
    @endforeach
  </div>
  @if($errors->has('participant_organizations.*'))
  <div class="text-smaller text-danger">{{ $errors->first('participant_organizations.*') }}</div>
  @endif
  <small class="form-text text-muted">Solo se ofrecen organizaciones activas; una inactiva aparece únicamente si la meta ya la tenía. Las organizaciones no otorgan permisos.</small>
  @endif
</fieldset>
