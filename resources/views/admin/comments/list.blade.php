@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Comentarios</h3>
  <p class="lead">Todos los comentarios y respuestas que los usuarios dejaron en los reportes de la plataforma.</p>
  @include('partials.comments.list', [
    'filterUrl' => route('admin.comments'),
    'downloadUrl' => route('admin.comments.download', request()->only(['q', 'tipo'])),
    'deleteRoute' => 'admin.comments.delete.form',
  ])
</section>

@endsection
