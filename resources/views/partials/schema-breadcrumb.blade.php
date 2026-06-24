<?php
$breadcrumbs = [
    "@context" => "https://schema.org",
    "@type" => "BreadcrumbList",
    "itemListElement" => [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => url('/')
        ]
    ]
];

$currentRoute = request()->route()?->getName();
$routeTitles = [
    'color-mechas' => 'Color y Mechas',
    'corte-tratamientos' => 'Corte y Tratamientos',
    'peinados-eventos' => 'Peinados para Eventos',
    'belleza-estetica' => 'Belleza y Estética',
    'contacto' => 'Contacto',
    'privacidad' => 'Política de Privacidad',
    'avisos-legales' => 'Avisos Legales',
    'cookies' => 'Política de Cookies',
];

if ($currentRoute && $currentRoute !== 'home' && isset($routeTitles[$currentRoute])) {
    $breadcrumbs['itemListElement'][] = [
        "@type" => "ListItem",
        "position" => 2,
        "name" => $routeTitles[$currentRoute],
        "item" => url()->current()
    ];
}
?>
<script type="application/ld+json">
{!! json_encode($breadcrumbs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
