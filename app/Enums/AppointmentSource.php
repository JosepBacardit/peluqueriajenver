<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Web = 'web';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Admin => 'Panel',
        };
    }
}
