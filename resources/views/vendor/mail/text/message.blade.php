<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            Peluquería Jenver · C/ Lleida, 21 · 08110 Montcada i Reixac · 633 912 050
            © {{ date('Y') }} Peluquería Jenver. Todos los derechos reservados.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
