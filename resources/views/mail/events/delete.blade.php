@component('mail::message')
# ¡Atención {{$user->name}}! 👏👏

Tenemos que informarte que han **eliminado** la actividad **{{$event->title}}** en el {{ config('app.short_name') }} 🗓️.

Como estas suscripto a un objetivo relacionado con la actividad, nos parecio que teniamos que avisarte. 😮

Muchas gracias, <br>
{{ config('app.name') }} 😉
@endcomponent