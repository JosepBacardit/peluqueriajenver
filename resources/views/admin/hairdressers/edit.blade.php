@extends('layouts.admin')

@section('title', 'Editar peluquera')

@section('content')
<h1 class="font-serif text-3xl text-white mb-6">Editar peluquera</h1>

<form method="POST" action="{{ route('admin.hairdressers.update', $hairdresser) }}" class="space-y-5 max-w-2xl" novalidate>
    @method('PUT')
    @include('admin.hairdressers._form')
</form>
@endsection
