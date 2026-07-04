<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * The three construction-service classes under PP 9/2022. EPC / design-build is
 * "integrated construction work" (jasa konstruksi terintegrasi) — its own class,
 * taxed differently from ordinary construction work or consulting.
 */
enum ServiceClass: string
{
    case ConstructionWork = 'construction_work';      // pelaksanaan konstruksi
    case IntegratedWork = 'integrated_work';          // pekerjaan konstruksi terintegrasi (EPC / design-build)
    case Consulting = 'consulting';                   // konsultansi konstruksi

    public function label(): string
    {
        return match ($this) {
            self::ConstructionWork => 'Pelaksanaan Konstruksi',
            self::IntegratedWork => 'Pekerjaan Konstruksi Terintegrasi (EPC)',
            self::Consulting => 'Konsultansi Konstruksi',
        };
    }
}
