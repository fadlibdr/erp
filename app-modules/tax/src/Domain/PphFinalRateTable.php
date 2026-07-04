<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * The canonical set of PPh-final rate rows. In the running application these are
 * loaded from the `tax_pph_final_rates` table; here the statutory defaults are
 * also expressed in code so the resolver can be unit-tested without a database
 * and so a fresh install has correct rates on day one (the seeder writes these).
 *
 * Sources (re-verified against primary references):
 *   - PP 9/2022 (eff. 2022-02-21): pajak.go.id/en/node/76970, peraturan.bpk.go.id/Details/199710
 *   - PP 51/2008 jo PP 40/2009 (the pre-2022-02-21 regime, kept for the transitional rule)
 */
final class PphFinalRateTable
{
    /** @var list<PphFinalRate> */
    private array $rates;

    /**
     * @param  list<PphFinalRate>  $rates
     */
    public function __construct(array $rates)
    {
        $this->rates = array_values($rates);
    }

    /** @return list<PphFinalRate> */
    public function all(): array
    {
        return $this->rates;
    }

    /** The statutory defaults shipped with the product. */
    public static function statutory(): self
    {
        $pp92022 = '2022-02-21';
        $dayBefore = '2022-02-20';

        return new self([
            // ---- PP 9/2022, effective 2022-02-21, open-ended --------------------
            // Construction work
            new PphFinalRate(ServiceClass::ConstructionWork, SbuClass::Small, 175, $pp92022, null, 'PP 9/2022'),
            new PphFinalRate(ServiceClass::ConstructionWork, SbuClass::MediumLargeSpecialist, 265, $pp92022, null, 'PP 9/2022'),
            new PphFinalRate(ServiceClass::ConstructionWork, SbuClass::None, 400, $pp92022, null, 'PP 9/2022'),
            // Integrated / EPC
            new PphFinalRate(ServiceClass::IntegratedWork, SbuClass::Small, 265, $pp92022, null, 'PP 9/2022'),
            new PphFinalRate(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, 265, $pp92022, null, 'PP 9/2022'),
            new PphFinalRate(ServiceClass::IntegratedWork, SbuClass::None, 400, $pp92022, null, 'PP 9/2022'),
            // Consulting
            new PphFinalRate(ServiceClass::Consulting, SbuClass::Small, 350, $pp92022, null, 'PP 9/2022'),
            new PphFinalRate(ServiceClass::Consulting, SbuClass::MediumLargeSpecialist, 350, $pp92022, null, 'PP 9/2022'),
            new PphFinalRate(ServiceClass::Consulting, SbuClass::None, 600, $pp92022, null, 'PP 9/2022'),

            // ---- PP 51/2008 jo PP 40/2009, the prior regime (transitional rule) --
            // A contract signed before 2022-02-21 stays on these rows. The old
            // regime had no separate "integrated" class and a flat 4% penalty.
            new PphFinalRate(ServiceClass::ConstructionWork, SbuClass::Small, 200, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::ConstructionWork, SbuClass::MediumLargeSpecialist, 300, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::ConstructionWork, SbuClass::None, 400, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::IntegratedWork, SbuClass::Small, 400, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, 400, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::IntegratedWork, SbuClass::None, 400, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::Consulting, SbuClass::Small, 400, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::Consulting, SbuClass::MediumLargeSpecialist, 400, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
            new PphFinalRate(ServiceClass::Consulting, SbuClass::None, 600, '2008-01-01', $dayBefore, 'PP 51/2008 jo PP 40/2009'),
        ]);
    }
}
