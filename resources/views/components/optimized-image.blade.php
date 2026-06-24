@props([
    'src' => '',
    'alt' => '',
    'lazy' => true,
    'preload' => false,
    'class' => '',
    'width' => null,
    'height' => null,
])

@if($preload)
<link rel="preload" as="image" href="{{ asset($src) }}" imagesrcset="{{ asset(str_replace('.png', '.webp', $src)) }} 1x">
@endif

<picture>
    <source srcset="{{ asset(str_replace(['.png', '.jpg', '.jpeg'], '.webp', $src)) }}" type="image/webp">
    <img
        src="{{ asset($src) }}"
        alt="{{ $alt }}"
        @if($lazy) loading="lazy" @endif
        @if($width) width="{{ $width }}" @endif
        @if($height) height="{{ $height }}" @endif
        class="{{ $class }}"
        decoding="async"
    >
</picture>
