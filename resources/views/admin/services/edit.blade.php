@extends('layouts.admin')

@section('title', 'Editar servicio')

@section('content')
<h1 class="font-serif text-3xl text-white mb-6">Editar servicio</h1>

{{-- novalidate (review M1): the server answers every mistake in plain
     words next to its step, never the browser's own bubble. --}}
<form method="POST" action="{{ route('admin.services.update', $service) }}" class="space-y-5 max-w-2xl" novalidate>
    @method('PUT')
    @include('admin.services._form')
</form>
@endsection
