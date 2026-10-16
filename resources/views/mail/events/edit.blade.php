@php
    $titleEncode = urlencode($event->title);
    $descriptionEncode = urlencode('Mas información: ' . route('events.index',['eventId' => $event->id]));
    $locationEncode = urlencode($event->address);
    $startTime = urlencode($event->date->format('Ymd\THis'));
    $endTime = urlencode($event->date->addHour()->format('Ymd\THis'));
    $ctz = urlencode('America/Argentina/Buenos_Aires');
@endphp

@component('mail::message')
# ¡Atención {{$user->name}}! 👏👏

Han editado la actividad **{{$event->title}}** en el {{ config('app.short_name') }} 🗓️ y como estas suscripto a un objetivo relacionado con la actividad, nos parecio oportuno avisarte. 😮

La actividad se llevará a cabo el @justdate($event->date) a las @justtime($event->date). Te invitamos a que veas la actividad editada en la web del {{ config('app.short_name') }} 👇

@component('mail::button', ['url' => route('events.index', ['eventId' => $event->id])])
🔍 Ver mas información
@endcomponent 

Si tenes que re-agendarla en tu calendario, ¡No te preocupes! Te dejamos un práctico boton para que la re-agendes en tu Google Calendar 👇

@component('mail::button', ['url' => 'https://calendar.google.com/calendar/r/eventedit?action=TEMPLATE&text='.$titleEncode.'&details='.$descriptionEncode.'&location='.$locationEncode.'&dates='.$startTime.'/'.$endTime.'&ctz='.$ctz])
📅 Agregar a Google Calendar 
@endcomponent

Muchas gracias, <br>
{{ config('app.name') }} 😉
@endcomponent