@extends('layouts.admin')

@section('title', 'Acceso')

@section('content')
<div class="max-w-sm mx-auto mt-12">
    <h1 class="font-serif text-3xl text-gold mb-6 text-center">Panel del salón</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4 bg-[#111111] border border-[#2A2A2A] p-6">
        @csrf

        <div>
            <label for="email" class="block text-sm mb-1">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full bg-black border border-[#2A2A2A] px-3 py-2 focus:border-gold focus:outline-none">
            @error('email')
                <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm mb-1">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full bg-black border border-[#2A2A2A] px-3 py-2 focus:border-gold focus:outline-none">
            @error('password')
                <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-300">
            <input type="checkbox" name="remember" value="1"> Mantener la sesión abierta
        </label>

        <button type="submit" class="btn-gold w-full">Entrar</button>
    </form>
</div>
@endsection
