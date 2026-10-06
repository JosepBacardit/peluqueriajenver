@extends('layouts.admin')

@section('title', 'Mi cuenta')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Mi cuenta</h1>
<p class="text-sm text-gray-400 mb-6">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>

@php($inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-3 min-h-11 focus:border-gold focus:outline-none')

<form method="POST" action="{{ route('admin.account.update-password') }}" class="space-y-5 max-w-xl">
    @csrf
    @method('PUT')

    <div>
        <label for="current_password" class="block text-sm mb-1">Contraseña actual</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="{{ $inputClass }}">
        @error('current_password') <p class="text-red-400 text-sm mt-1" role="alert">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password" class="block text-sm mb-1">Contraseña nueva (al menos {{ \App\Models\User::MIN_PASSWORD_LENGTH }} caracteres)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="{{ \App\Models\User::MIN_PASSWORD_LENGTH }}" class="{{ $inputClass }}">
        @error('password') <p class="text-red-400 text-sm mt-1" role="alert">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm mb-1">Repite la contraseña nueva</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="{{ \App\Models\User::MIN_PASSWORD_LENGTH }}" class="{{ $inputClass }}">
    </div>

    <p class="text-xs text-gray-400">Al cambiarla, se cierran las demás sesiones abiertas con esta cuenta en otros dispositivos.</p>

    <button type="submit" class="btn-gold">Cambiar contraseña</button>
</form>
@endsection
