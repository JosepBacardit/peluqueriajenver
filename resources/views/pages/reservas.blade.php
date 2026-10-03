@extends('layouts.app')

@section('title', __('reservas.meta.title'))
@section('meta_description', __('reservas.meta.description'))
@section('og_title', __('reservas.meta.title'))
@section('og_description', __('reservas.meta.description'))
@section('canonical', route('reservas'))

@section('content')
<section class="bg-[#111111] py-12 md:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl font-bold text-white mb-4">{{ __('reservas.hero.title') }}</h1>
        <p class="text-gray-400 text-lg">{{ __('reservas.hero.subtitle') }}</p>
    </div>
</section>

<section class="bg-[#0A0A0A] py-12 md:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        @if (session('status'))
            <p role="status" class="border border-gold/40 bg-gold/10 text-gold-light px-4 py-3">{{ session('status') }}</p>
        @endif

        @error('booking')
            <p role="alert" class="border border-red-500/50 bg-red-500/10 text-red-200 px-4 py-3">{{ $message }}</p>
        @enderror

        @if ($services->isEmpty())
            <p class="text-gray-300 text-lg">{{ __('reservas.no_services') }}</p>
        @elseif ($service === null)
            {{-- Step 1: service --}}
            <div>
                <h2 class="font-serif text-2xl text-white mb-6">{{ __('reservas.steps.service') }}</h2>
                <ul class="grid sm:grid-cols-2 gap-4">
                    @foreach ($services as $item)
                        <li>
                            <a href="{{ route('reservas', ['servicio' => $item->id]) }}" class="block border border-[#2A2A2A] hover:border-gold p-5 transition-colors">
                                <span class="block text-white font-semibold">{{ $item->name }}</span>
                                <span class="block text-sm text-gray-400 mt-1">{{ $item->duration_label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-4 border border-[#2A2A2A] p-4">
                <p class="text-white"><span class="text-gold">{{ $service->name }}</span> · {{ $service->duration_label }}</p>
                <a href="{{ route('reservas') }}" class="text-sm text-gray-300 hover:text-gold underline">{{ __('reservas.change_service') }}</a>
            </div>

            {{-- Step 2: day --}}
            @include('pages.partials.reservas-calendar')

            {{-- Step 3: time and details --}}
            @if ($day !== null)
                @include('pages.partials.reservas-form')
            @elseif ($requestedDay !== null)
                <p class="text-gray-300">{{ __('reservas.calendar.day_full') }}</p>
                @error('time') <p class="text-red-400">{{ $message }}</p> @enderror
            @endif
        @endif
    </div>
</section>
@endsection
