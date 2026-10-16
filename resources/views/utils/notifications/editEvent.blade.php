<h5 class="mb-2 is-700"><i class="fas fa-calendar-days fa-fw"></i><i class="fas fa-pencil fa-fw"></i> Han editado una actividad</h5>
<p class="my-1 text-smaller">Han editado la actividad <b>{{$notification->data['event']['title']}}</b>, te notificamos porque la actividad está relacionada con un objetivo al que estas suscripto. Podes ver mas acerca de la actividad haciendo <a href="{{route('events.index', ['eventId' => $notification->data['event']['id']])}}" >click aquí</a></p>
<p class="my-1 text-smallest text-muted">
Notificado el @datetime($notification->created_at) - <a
  href="{{route('events.index', ['eventId' => $notification->data['event']['id']])}}" title="{{$notification->data['event']['title']}}">Ver actividad</a>
</p>