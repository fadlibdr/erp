<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * The monthly TER (Tarif Efektif Rata-rata) category under PMK 168/2023. Every
 * PTKP status maps to one of three monthly rate schedules:
 *   A — TK/0, TK/1, K/0
 *   B — TK/2, TK/3, K/1, K/2
 *   C — K/3
 */
enum TerCategory: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public static function forStatus(PtkpStatus $status): self
    {
        return match ($status) {
            PtkpStatus::TK0, PtkpStatus::TK1, PtkpStatus::K0 => self::A,
            PtkpStatus::TK2, PtkpStatus::TK3, PtkpStatus::K1, PtkpStatus::K2 => self::B,
            PtkpStatus::K3 => self::C,
        };
    }
}
