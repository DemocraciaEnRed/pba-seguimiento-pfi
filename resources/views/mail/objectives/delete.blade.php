@component('mail::message')
# ¡Atención {{$user->name}}! 👏👏

Tenemos que informarte que han **eliminado** el objetivo **{{$objective->title}}** en el {{ config('app.short_name') }}.

Como estas suscripto al objetivo, nos parecio importante avisarte. 😮

Muchas gracias, <br>
{{ config('app.name') }} 😉
@endcomponent