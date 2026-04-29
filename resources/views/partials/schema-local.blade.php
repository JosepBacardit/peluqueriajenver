<?php
$schema = [
    "@context" => "https://schema.org",
    "@type" => "HairSalon",
    "name" => "Peluquería Jenver",
    "description" => "Peluquería unisex en Montcada i Reixac especializada en balayage, cabello afro, rizos y coloración capilar.",
    "url" => url('/'),
    "telephone" => "+34633912050",
    "email" => "",
    "image" => asset('images/logo-jenver.png'),
    "logo" => asset('images/logo-jenver.png'),
    "address" => [
        "@type" => "PostalAddress",
        "streetAddress" => "C/ Lleida, 21",
        "addressLocality" => "Montcada i Reixac",
        "addressRegion" => "Barcelona",
        "postalCode" => "08110",
        "addressCountry" => "ES"
    ],
    "geo" => [
        "@type" => "GeoCoordinates",
        "latitude" => 41.4897,
        "longitude" => 2.1898
    ],
    "openingHoursSpecification" => [
        [
            "@type" => "OpeningHoursSpecification",
            "dayOfWeek" => ["Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
            "opens" => "09:00",
            "closes" => "19:00"
        ],
        [
            "@type" => "OpeningHoursSpecification",
            "dayOfWeek" => ["Monday", "Sunday"],
            "opens" => "00:00",
            "closes" => "00:00"
        ]
    ],
    "priceRange" => "€€",
    "currenciesAccepted" => "EUR",
    "paymentAccepted" => "Cash, Credit Card",
    "areaServed" => ["Montcada i Reixac", "Ripollet", "Cerdanyola del Vallès", "Santa Coloma de Gramenet"],
    "hasMap" => "https://maps.google.com/?q=Peluquería+Jenver+Carrer+Lleida+21+Montcada+i+Reixac",
    "sameAs" => [
        "https://www.instagram.com/peluqueriajenver/",
        "https://www.tiktok.com/@peluqueriajenver"
    ],
    "makesOffer" => [
        ["@type" => "Offer", "itemOffered" => ["@type" => "Service", "name" => "Balayage"]],
        ["@type" => "Offer", "itemOffered" => ["@type" => "Service", "name" => "Coloración capilar"]],
        ["@type" => "Offer", "itemOffered" => ["@type" => "Service", "name" => "Cabello afro"]],
        ["@type" => "Offer", "itemOffered" => ["@type" => "Service", "name" => "Corte de pelo"]],
        ["@type" => "Offer", "itemOffered" => ["@type" => "Service", "name" => "Peinado para eventos"]]
    ]
];
?>
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
