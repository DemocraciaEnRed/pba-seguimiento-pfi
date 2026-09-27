@component('mail::message')
# ¡Hola {{$user->name}}! 👋

¡Te comentamos que hay una nueva meta ⭐ en el objetivo **{{$objective->title}}** al cual estás suscripto!

@component('mail::panel')
# 🎯 **{{$goal->title}}**

**Acerca del indicador**

{{Str::limit($goal->indicator, 200, $end=' [...]')}}
@endcomponent

@component('mail::table')
| Acerca de | Valor |
|:--------:|:--------:|
| **Estado** | {{$goal->status_label }} |
@if($goal->isSimple())
| **Valor a alcanzar** | {{ indicator_number($goal->indicator_goal) }} |
| **Unidad del indicador** | {{$goal->indicator_unit }} |
| **Frecuencia** | {{$goal->indicator_frequency }} |
@elseif($goal->isPeriodic())
| **Unidad del indicador** | {{$goal->indicator_unit }} |
| **Seguimiento** | {{ $goal->period_count }} períodos, {{ mb_strtolower($goal->period_type->label()) }} |
@endif
@endcomponent

La nueva meta 🎯 **{{$goal->title}}** y podes entrar a ver todo acerca de ella en la web de Participes haciendo clic en el botón 👇

@component('mail::button', ['url' => route('goals.index', ['goalId' => $goal->id])])
🔍 Ver meta
@endcomponent

@if(!$goal->milestones->isEmpty())
Estos son los hitos con la cual la meta fue creada

@component('mail::table')
| Hitos |
|:-----:|
@foreach ($goal->milestones as $milestone)
| 🏆 {{$milestone->title}} |
@endforeach
@endcomponent
@endif

Muchas gracias, <br>
{{ config('app.name') }} 😉
@endcomponent
