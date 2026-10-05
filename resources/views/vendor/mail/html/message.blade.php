<x-mail::layout>
{{-- Header: the salon's own logo, not the framework's, as an absolute
     public URL so it still shows up when a mail client blocks images
     loaded from the message itself. logo-jenver-email.png is the website
     logo at twice its 124x56 display size (PNG, the approved exception
     for emails), made from public/images/logo-jenver.png with
     `magick logo-jenver.png -resize 248x112 -strip -colors 192 logo-jenver-email.png`.
     The size is in the attributes because Outlook ignores CSS sizes. --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<img src="{{ asset('images/logo-jenver-email.png') }}" class="logo" alt="Peluquería Jenver" width="124" height="56">
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
