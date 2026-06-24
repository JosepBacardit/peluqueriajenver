<?php
$faq = [
    "@context" => "https://schema.org",
    "@type" => "FAQPage",
    "mainEntity" => [
        [
            "@type" => "Question",
            "name" => "¿Cuál es la diferencia entre balayage y mechas?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "El balayage es una técnica de mano alzada que crea un efecto más natural y degradado. Las mechas clásicas tienen una separación definida y más contraste."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "¿Puedo agendar cita online?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Sí, puedes contactarnos por WhatsApp al 633 912 050 o llamarnos directamente. Nuestro horario es de martes a sábado de 9:00 a 19:00."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "¿Atienden cabello afro y rizado?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Sí, somos especialistas en cabello afro y rizos. Contamos con técnicas especializadas y profesionales con experiencia en todos los tipos de cabello."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "¿Tienen estacionamiento?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Sí, contamos con estacionamiento disponible en la zona. Ubicados en Carrer Lleida, 21 - Montcada i Reixac."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "¿Cuánto tiempo dura un corte de pelo?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Un corte de mujer dura entre 30-45 minutos. Un corte de caballero entre 20-30 minutos. Te recomendamos reservar con anticipación."
            ]
        ]
    ]
];
?>
<script type="application/ld+json">
{!! json_encode($faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
