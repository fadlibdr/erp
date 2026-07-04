<?php

declare(strict_types=1);

namespace Modules\Platform\Domain;

/**
 * Supported currencies and their minor-unit scale.
 *
 * IDR is tracked as whole rupiah (scale 0): in construction billing the sen is
 * never used, and keeping IDR at scale 0 removes a whole class of rounding noise.
 * Foreign currencies (imported plant, some O&G EPC contracts) keep two decimals.
 */
enum Currency: string
{
    case IDR = 'IDR';
    case USD = 'USD';
    case EUR = 'EUR';
    case SGD = 'SGD';
    case JPY = 'JPY';

    /** Number of decimal places stored in the minor unit. */
    public function scale(): int
    {
        return match ($this) {
            self::IDR, self::JPY => 0,
            self::USD, self::EUR, self::SGD => 2,
        };
    }

    /** 10 ** scale — the number of minor units in one major unit. */
    public function subunits(): int
    {
        return 10 ** $this->scale();
    }
}
