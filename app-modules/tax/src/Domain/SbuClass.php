<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * The contractor's business-qualification standing that drives the PPh-final rate.
 *
 * The PPh rate keys off *which qualification the provider holds* at the time of
 * the payment, not the project size — so this is modelled as an explicit
 * classification rather than derived. `None` means no valid SBU / competency
 * certificate, which carries the penalty rate.
 */
enum SbuClass: string
{
    case Small = 'small';                             // kualifikasi kecil (K) / individual competency cert
    case MediumLargeSpecialist = 'medium_large_spec'; // menengah / besar / spesialis
    case None = 'none';                               // tanpa SBU / tanpa sertifikat kompetensi

    public function hasCertificate(): bool
    {
        return $this !== self::None;
    }

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Kualifikasi Kecil',
            self::MediumLargeSpecialist => 'Menengah / Besar / Spesialis',
            self::None => 'Tanpa Kualifikasi',
        };
    }
}
