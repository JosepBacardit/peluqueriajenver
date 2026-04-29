<?php

return [
    // Meta tags
    'meta' => [
        'title' => 'Peluquería Jenver | Especialistas en Balayage, Afro y Rizos',
        'description' => 'Peluquería unisex en Montcada i Reixac. Especialistas en balayage, mechas, cabello afro, rizos y coloración. Corte de pelo hombre y mujer. Reserva: 633 912 050.',
        'keywords' => 'peluquería Montcada i Reixac, balayage, cabello afro, especialista rizos, peluquería unisex, corte de pelo',
    ],

    // Hero Section
    'hero' => [
        'location' => '✨ Peluquería Unisex Jenver en Montcada i Reixac',
        'title' => 'Tu pelo, en manos de confianza.',
        'subtitle' => 'Atención personalizada, precios honestos y resultados que te gustan de verdad.',
        'cta_primary' => 'Reservar cita →',
        'cta_secondary' => 'Ver servicios',
    ],

    // Servicios Intro Section (después del hero)
    'servicios_intro' => [
        'title' => 'Nuestros servicios',
        'subtitle' => 'Todo lo que necesita tu pelo — y algo más',
        'description' => 'Especialistas en color y mechas, con servicio completo para que no tengas que ir a ningún otro sitio.',
        'cards' => [
            'color' => [
                'icon' => '✦',
                'label' => 'Lo que más hacemos',
                'title' => 'Color y mechas',
                'description' => 'Resultados naturales o de impacto, según lo que busques.',
                'url' => '/color-y-mechas',
                'services' => ['Balayage', 'Babylights', 'Mechas clásicas', 'Coloración completa', 'Retoque de raíz', 'Decoloración'],
            ],
            'corte' => [
                'icon' => '✂',
                'title' => 'Corte y tratamientos',
                'description' => 'Corte para mujer, caballero y niños. Alisados duraderos.',
                'url' => '/corte-y-tratamientos',
                'services' => ['Corte de mujer', 'Corte de caballero', 'Corte de niños', 'Alisado permanente', 'Keratina', 'Arreglo de barba'],
            ],
            'peinado' => [
                'icon' => '💇',
                'title' => 'Peinado y eventos',
                'description' => 'Para el día más especial o cuando simplemente quieres brillar.',
                'url' => '/peinados-eventos',
                'services' => ['Peinado para bodas', 'Peinado para eventos', 'Recogidos', 'Ondas y rizos'],
            ],
            'belleza' => [
                'icon' => '💅',
                'title' => 'Belleza y estética',
                'description' => 'Para cuidarte de la cabeza a los pies, sin salir del salón.',
                'url' => '/belleza-estetica',
                'services' => ['Manicura', 'Pedicura', 'Diseño de cejas', 'Depilación facial'],
            ],
        ],
        'diagnostico_title' => 'Diagnóstico personalizado incluido',
        'diagnostico_description' => 'Antes de empezar, analizamos tu pelo y te decimos exactamente qué te puede quedar mejor. Sin compromiso.',
        'diagnostico_cta' => 'Gratis en tu primera visita',
    ],

    // Especialidades Section
    'especialidades' => [
        'title' => 'Nuestras especialidades',
        'cards' => [
            'balayage' => [
                'icon' => '✦',
                'title' => 'Balayage & Color',
                'description' => 'Especialistas en balayage, mechas y coloración capilar. Técnicas de degradado natural y color personalizado.',
            ],
            'afro' => [
                'icon' => '◈',
                'title' => 'Cabello Afro y Rizos',
                'description' => 'Especialistas en cabello afro y rizado. Peinados, definición y cuidado de todo tipo de texturas.',
            ],
            'corte' => [
                'icon' => '✂',
                'title' => 'Corte & Estilismo',
                'description' => 'Corte de pelo para hombre, mujer y niños. Extensiones y tratamientos capilares personalizados.',
            ],
        ],
    ],

    // Todos los Servicios Section
    'todos_servicios' => [
        'title' => 'Todos nuestros servicios',
        'subtitle' => 'Listado completo de servicios y precios',
        'items' => [
            [
                'name' => 'Corte de pelo mujer',
                'price' => 'Desde €20',
                'category' => 'Corte',
            ],
            [
                'name' => 'Corte de pelo hombre',
                'price' => 'Desde €15',
                'category' => 'Corte',
            ],
            [
                'name' => 'Corte infantil',
                'price' => 'Desde €12',
                'category' => 'Corte',
            ],
            [
                'name' => 'Balayage',
                'price' => 'Desde €60',
                'category' => 'Color',
            ],
            [
                'name' => 'Mechas / Highlights',
                'price' => 'Desde €45',
                'category' => 'Color',
            ],
            [
                'name' => 'Coloración completa',
                'price' => 'Desde €40',
                'category' => 'Color',
            ],
            [
                'name' => 'Retoque de raíz',
                'price' => 'Desde €30',
                'category' => 'Color',
            ],
            [
                'name' => 'Decoloración',
                'price' => 'Consultar',
                'category' => 'Color',
            ],
            [
                'name' => 'Peinado cabello afro',
                'price' => 'Consultar',
                'category' => 'Peinado',
            ],
            [
                'name' => 'Definición de rizos',
                'price' => 'Consultar',
                'category' => 'Peinado',
            ],
            [
                'name' => 'Peinado para bodas',
                'price' => 'Consultar',
                'category' => 'Peinado',
            ],
            [
                'name' => 'Peinado para eventos',
                'price' => 'Consultar',
                'category' => 'Peinado',
            ],
            [
                'name' => 'Extensiones de cabello',
                'price' => 'Consultar',
                'category' => 'Tratamientos',
            ],
            [
                'name' => 'Tratamiento keratina',
                'price' => 'Consultar',
                'category' => 'Tratamientos',
            ],
            [
                'name' => 'Alisado permanente',
                'price' => 'Consultar',
                'category' => 'Tratamientos',
            ],
            [
                'name' => 'Manicura',
                'price' => 'Desde €15',
                'category' => 'Belleza',
            ],
            [
                'name' => 'Pedicura',
                'price' => 'Desde €20',
                'category' => 'Belleza',
            ],
            [
                'name' => 'Diseño de cejas',
                'price' => 'Desde €10',
                'category' => 'Belleza',
            ],
        ],
    ],

    // Galería Section
    'galeria' => [
        'title' => 'Nuestros trabajos',
        'subtitle' => 'Síguenos en Instagram @peluqueria_jenver',
        'cta' => 'Ver más en Instagram →',
    ],

    // Servicios Section
    'servicios' => [
        'title' => 'Servicios',
        'items' => [
            'mujer_cut' => 'Corte de pelo mujer',
            'hombre_cut' => 'Corte de pelo hombre',
            'nino_cut' => 'Corte infantil',
            'balayage' => 'Balayage',
            'highlights' => 'Mechas / Highlights',
            'coloracion' => 'Coloración completa',
            'afro_peinado' => 'Peinado cabello afro',
            'rizos' => 'Definición de rizos',
            'extensiones' => 'Extensiones de cabello',
            'keratina' => 'Tratamiento keratina',
        ],
        'prices' => [
            'mujer_cut' => 'Desde €20',
            'hombre_cut' => 'Desde €15',
            'nino_cut' => 'Desde €12',
            'balayage' => 'Desde €60',
            'highlights' => 'Desde €45',
            'coloracion' => 'Desde €40',
            'afro_peinado' => 'Consultar',
            'rizos' => 'Consultar',
            'extensiones' => 'Consultar',
            'keratina' => 'Consultar',
        ],
        'help_text' => '¿Tienes dudas sobre el precio? <a href="tel:+34633912050" class="text-gold hover:text-gold-light transition-colors font-semibold">Llámanos: 633 912 050</a>',
    ],

    // Nosotros Section
    'nosotros' => [
        'title' => '¿Por qué Peluquería Jenver?',
        'stats' => [
            'experience' => ['number' => '10+', 'label' => 'Años de experiencia'],
            'unisex' => ['number' => 'Unisex', 'label' => 'Para toda la familia'],
            'numero_uno' => ['number' => '#1', 'label' => 'Especialistas afro'],
            'rating' => ['number' => '5★', 'label' => 'Valoración en Google'],
        ],
        'description' => 'Somos la peluquería de referencia para cabello afro, rizos y técnicas de color como el balayage. Atendemos a clientes de Montcada i Reixac, Ripollet, Cerdanyola del Vallès, Santa Coloma de Gramenet y toda la zona norte de Barcelona.',
    ],

    // Reseñas Section
    'resenas' => [
        'title' => 'Qué dicen nuestros clientes',
        'reviews' => [
            [
                'text' => 'El mejor balayage que me han hecho nunca. Vine desde Barcelona norte porque me lo recomendaron y repetiré sin duda. Ambiente muy acogedor.',
                'author' => 'María G.',
            ],
            [
                'text' => 'Especialistas en cabello afro de verdad. Llevan años cuidando mi pelo y siempre salen perfectos los peinados. Los recomiendo a todo el mundo.',
                'author' => 'Aminata K.',
            ],
            [
                'text' => 'Fui a por un corte y me quedé con el color también. El trato es increíble y el resultado espectacular. Sin duda la mejor peluquería de Montcada i Reixac.',
                'author' => 'Laura M.',
            ],
        ],
    ],

    // CTA Reserva Section
    'reserva' => [
        'subtitle' => '¿Lista para tu cambio de look?',
        'title' => 'Reserva tu cita<br>en Peluquería Jenver',
        'description' => 'Atendemos en C/ Lleida, 21 · Montcada i Reixac · Barcelona<br>Abiertos de martes a sábado, de 9:00 a 19:00',
        'phone_cta' => 'Llamar ahora: 633 912 050',
        'whatsapp_cta' => 'WhatsApp',
    ],

    // Contacto Section
    'contacto' => [
        'title' => 'Cómo llegar',
        'business_name' => 'Peluquería Jenver',
        'address_label' => 'Dirección',
        'address_street' => 'C/ Lleida, 21',
        'address_postal' => '08110 Montcada i Reixac',
        'address_city' => 'Barcelona, España',
        'phone_label' => 'Teléfono',
        'hours_label' => 'Horario',
        'hours' => 'Martes a Sábado: 9:00 – 19:00<br>Lunes y Domingo: Cerrado',
        'maps_link' => 'Ver en Google Maps →',
    ],
];
