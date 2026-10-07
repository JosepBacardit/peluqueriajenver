<?php
$schema = [
    "@context" => "https://schema.org",
    "@type" => ["HairSalon", "Organization"],
    "name" => "Peluquería Jenver",
    "description" => "Peluquería unisex en Montcada i Reixac especializada en balayage, cabello afro, rizos y coloración capilar.",
    "url" => url('/'),
    "telephone" => "+34633912050",
    "contactPoint" => [
        "@type" => "ContactPoint",
        "telephone" => "+34633912050",
        "contactType" => "Customer Service",
        "areaServed" => "Montcada i Reixac",
        "availableLanguage" => ["es", "ca"]
    ],
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
    "openingHoursSpecification" => \App\Booking\OpeningHoursSummary::schemaSpecifications(),
    "currenciesAccepted" => "EUR",
    "paymentAccepted" => "Cash, Credit Card",
    "areaServed" => ["Montcada i Reixac", "Ripollet", "Cerdanyola del Vallès", "Santa Coloma de Gramenet"],
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
    ],
    "hasMap" => [
        "@type" => "Map",
        "url" => "https://maps.google.com/?q=Peluquería+Jenver+Carrer+Lleida+21+Montcada+i+Reixac"
    ]
];

// The ReserveAction only makes sense while /reservas actually takes
// bookings (the "Reserva online activa" switch on, and at least one
// service reservable online, PRF-147). Otherwise the action is simply
// omitted rather than pointed somewhere schema.org has no action type
// for ("call this number"); the phone number stays in `telephone` and
// `contactPoint` regardless.
if (\App\Models\BookingSetting::onlineBookingAvailable()) {
    $schema['potentialAction'] = [
        "@type" => "ReserveAction",
        "name" => "Reservar cita",
        "target" => [
            "@type" => "EntryPoint",
            "urlTemplate" => route('reservas')
        ]
    ];
}
?>
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
