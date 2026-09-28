@component('mail::message')
# ¡Atención {{$user->name}}! 👏👏

Tenemos que informarte que han **eliminado** el evento **{{$event->title}}** en el {{ config('app.short_name') }} 🗓️.

Como estas suscripto a un objetivo relacionado con el evento, nos parecio que teniamos que avisarte. 😮

Muchas gracias, <br>
{{ config('app.name') }} 😉
@endcomponent