<x-mail::layout>
{{-- Header: the salon's own logo, not the framework's, as an absolute
     public URL so it still shows up when a mail client blocks images
     loaded from the message itself. --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<img src="{{ asset('images/logo-jenver-optimized-v2.png') }}" class="logo" alt="Peluquería Jenver" width="160">
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: the salon's own contact details, not a framework copyright
     notice. --}}
<x-slot:footer>
<x-mail::footer>
Peluquería Jenver · C/ Lleida, 21 · 08110 Montcada i Reixac · 633 912 050<br>
© {{ date('Y') }} Peluquería Jenver. Todos los derechos reservados.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
