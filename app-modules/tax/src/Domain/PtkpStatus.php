<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * PTKP status — marital status + number of dependents (tanggungan, max 3) — which
 * fixes the annual non-taxable income and, under PMK 168/2023, the monthly TER
 * category an employee's withholding is read from.
 */
enum PtkpStatus: string
{
    case TK0 = 'TK/0';
    case TK1 = 'TK/1';
    case TK2 = 'TK/2';
    case TK3 = 'TK/3';
    case K0 = 'K/0';
    case K1 = 'K/1';
    case K2 = 'K/2';
    case K3 = 'K/3';
}
