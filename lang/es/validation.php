<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Spanish)
    |--------------------------------------------------------------------------
    |
    | Spanish versions of the rules this application uses. Any rule missing
    | here falls back to lang/en/validation.php.
    |
    */

    'accepted' => 'Debes aceptar :attribute.',
    'after' => ':Attribute debe ser posterior a :date.',
    'after_or_equal' => ':Attribute debe ser igual o posterior a :date.',
    'array' => ':Attribute debe ser una lista.',
    'before' => ':Attribute debe ser anterior a :date.',
    'before_or_equal' => ':Attribute debe ser igual o anterior a :date.',
    'between' => [
        'array' => ':Attribute debe tener entre :min y :max elementos.',
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'string' => ':Attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => ':Attribute debe ser sí o no.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => ':Attribute no es una fecha válida.',
    'date_format' => ':Attribute no tiene el formato :format.',
    'decimal' => ':Attribute debe tener como máximo :decimal decimales.',
    'different' => ':Attribute y :other deben ser distintos.',
    'digits' => ':Attribute debe tener :digits dígitos.',
    'digits_between' => ':Attribute debe tener entre :min y :max dígitos.',
    'email' => ':Attribute debe ser una dirección de email válida.',
    'exists' => ':Attribute no es válido.',
    'gt' => [
        'numeric' => ':Attribute debe ser mayor que :value.',
        'string' => ':Attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'numeric' => ':Attribute debe ser igual o mayor que :value.',
        'string' => ':Attribute debe tener al menos :value caracteres.',
    ],
    'in' => ':Attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lt' => [
        'numeric' => ':Attribute debe ser menor que :value.',
    ],
    'lte' => [
        'numeric' => ':Attribute debe ser igual o menor que :value.',
    ],
    'max' => [
        'array' => ':Attribute no puede tener más de :max elementos.',
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'array' => ':Attribute debe tener al menos :min elementos.',
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'multiple_of' => ':Attribute debe ser múltiplo de :value.',
    'numeric' => ':Attribute debe ser un número.',
    'present' => ':Attribute debe estar presente.',
    'prohibited' => ':Attribute no está permitido.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':Attribute es obligatorio.',
    'required_with' => ':Attribute es obligatorio cuando se indica :values.',
    'same' => ':Attribute y :other deben coincidir.',
    'string' => ':Attribute debe ser un texto.',
    'unique' => 'Ya existe un registro con ese :attribute.',

    'custom' => [],

    'attributes' => [
        'email' => 'el email',
        'password' => 'la contraseña',
        'name' => 'el nombre',
        'duration_minutes' => 'la duración',
        'price' => 'el precio interno',
        'sort_order' => 'el orden',
        'capacity' => 'la capacidad',
        'slot_interval_minutes' => 'el intervalo entre horas',
        'min_notice_minutes' => 'la antelación mínima',
        'max_advance_days' => 'la antelación máxima',
        'cancellation_limit_hours' => 'el plazo para cancelar',
        'starts_at' => 'el inicio',
        'ends_at' => 'el fin',
        'capacity_reduction' => 'la reducción de capacidad',
        'reason' => 'el motivo',
        'service_id' => 'el servicio',
        'date' => 'la fecha',
        'time' => 'la hora',
        'customer_name' => 'el nombre',
        'customer_phone' => 'el teléfono',
        'customer_email' => 'el email',
        'notes' => 'las observaciones',
        'privacy' => 'la información sobre protección de datos',
    ],

];
