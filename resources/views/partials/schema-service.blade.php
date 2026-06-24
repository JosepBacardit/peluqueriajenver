<?php
$services = [
    'color-mechas' => [
        'name' => 'Balayage y Color',
        'description' => 'Técnica de coloración profesional que crea un efecto natural y degradado. Especialistas en balayage, babylights y mechas clásicas con diagnóstico gratuito personalizado.',
        'items' => ['Balayage', 'Babylights', 'Mechas Clásicas', 'Coloración Completa', 'Retoque de Raíz']
    ],
    'corte-tratamientos' => [
        'name' => 'Corte y Tratamientos',
        'description' => 'Servicios de corte profesional para hombre, mujer y niños. Tratamientos capilares como keratina y alisados duraderos con técnicas especializadas.',
        'items' => ['Corte de Mujer', 'Corte de Caballero', 'Corte Infantil', 'Alisado Permanente', 'Keratina']
    ],
    'peinados-eventos' => [
        'name' => 'Peinados para Eventos',
        'description' => 'Peinados personalizados para bodas, eventos especiales y ocasiones importantes. Incluye prueba previa y diseño exclusivo para tu día perfecto.',
        'items' => ['Peinado para Bodas', 'Peinado para Eventos', 'Recogidos', 'Ondas y Rizos']
    ],
    'belleza-estetica' => [
        'name' => 'Belleza y Estética',
        'description' => 'Servicios complementarios de belleza integral: manicura, pedicura, diseño de cejas y depilación facial. Todo en un solo lugar profesional.',
        'items' => ['Manicura', 'Pedicura', 'Diseño de Cejas', 'Depilación Facial']
    ]
];

$currentRoute = request()->route()?->getName();
$serviceData = $services[$currentRoute] ?? null;

if ($serviceData):
    $service = [
        "@context" => "https://schema.org",
        "@type" => "Service",
        "name" => $serviceData['name'],
        "description" => $serviceData['description'],
        "provider" => [
            "@type" => "LocalBusiness",
            "name" => "Peluquería Jenver",
            "image" => asset('images/logo-jenver.png'),
            "telephone" => "+34633912050",
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => "C/ Lleida, 21",
                "addressLocality" => "Montcada i Reixac",
                "postalCode" => "08110",
                "addressCountry" => "ES"
            ]
        ],
        "areaServed" => "Montcada i Reixac",
        "hasOfferCatalog" => [
            "@type" => "OfferCatalog",
            "name" => "Servicios de " . $serviceData['name'],
            "itemListElement" => array_map(function($item) {
                return [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => $item
                    ]
                ];
            }, $serviceData['items'])
        ]
    ];
?>
<script type="application/ld+json">
{!! json_encode($service, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
<?php endif; ?>
