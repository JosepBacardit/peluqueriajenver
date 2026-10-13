@extends('layouts.admin')

@section('title', 'Nueva peluquera')

@section('content')
<h1 class="font-serif text-3xl text-white mb-6">Nueva peluquera</h1>

<form method="POST" action="{{ route('admin.hairdressers.store') }}" class="space-y-5 max-w-2xl" novalidate>
    @include('admin.hairdressers._form')
</form>
@endsection
