<?php
$aggregateRating = [
    "@context" => "https://schema.org",
    "@type" => "LocalBusiness",
    "name" => "Peluquería Jenver",
    "image" => asset('images/logo-jenver.png'),
    "telephone" => "+34633912050",
    "url" => url('/'),
    "address" => [
        "@type" => "PostalAddress",
        "streetAddress" => "C/ Lleida, 21",
        "addressLocality" => "Montcada i Reixac",
        "addressRegion" => "Barcelona",
        "postalCode" => "08110",
        "addressCountry" => "ES"
    ],
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => "5",
        "ratingCount" => "3",
        "bestRating" => "5",
        "worstRating" => "1"
    ],
    "review" => [
        [
            "@type" => "Review",
            "reviewRating" => [
                "@type" => "Rating",
                "ratingValue" => "5",
                "bestRating" => "5",
                "worstRating" => "1"
            ],
            "author" => [
                "@type" => "Person",
                "name" => "Alicia Egea"
            ],
            "reviewBody" => "No sabía dónde hacerme un alisado de keratina y decidí confiar en ellas… ¡y no puedo estar más contenta con el resultado! Son unas auténticas profesionales, trabajan de forma increíble y además el trato es inmejorable. Te hacen sentir cómoda desde el primer momento. Sin duda, repetiré. ¡Súper recomendadas! 💖",
            "datePublished" => "2025-04-22"
        ],
        [
            "@type" => "Review",
            "reviewRating" => [
                "@type" => "Rating",
                "ratingValue" => "5",
                "bestRating" => "5",
                "worstRating" => "1"
            ],
            "author" => [
                "@type" => "Person",
                "name" => "Josep Bacardit"
            ],
            "reviewBody" => "La peluquería de confianza en Montcada i Reixac — Jenver es una joya. Llevo tiempo viniendo y no la cambiaría por nada. Lo que más me sorprende es que no hace falta explicar nada: conocen perfectamente mi cabello y saben exactamente lo que necesito. El trato es exquisito: son atentas, cuidadosas y te hacen sentir cómodo desde el primer momento. Si buscas una peluquería que combine profesionalidad, mimo al detalle y un trato cercano, Jenver es la respuesta. Totalmente recomendada.",
            "datePublished" => "2025-04-22"
        ],
        [
            "@type" => "Review",
            "reviewRating" => [
                "@type" => "Rating",
                "ratingValue" => "5",
                "bestRating" => "5",
                "worstRating" => "1"
            ],
            "author" => [
                "@type" => "Person",
                "name" => "Raquel Iglesias"
            ],
            "reviewBody" => "Encantada con esta pelu. La verdad que tanto Jenny como su madre te hacen sentir súper a gusto. Probé primero corte y hidratación y ahora ya voy por alisado. En todo momento te explican todo. Se ha convertido en mi pelu de confianza 😊",
            "datePublished" => "2025-04-22"
        ]
    ]
];
?>
<script type="application/ld+json">
{!! json_encode($aggregateRating, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
