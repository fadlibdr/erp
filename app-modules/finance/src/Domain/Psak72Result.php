<?php

declare(strict_types=1);

namespace Modules\Finance\Domain;

use Modules\Platform\Domain\Money;

final class Psak72Result
{
    public function __construct(
        public readonly int $pocRatioPpm,
        public readonly Money $recognizedToDate,
        public readonly Money $periodRecognition,
        public readonly Money $billedToDate,
        public readonly Money $contractAsset,      // unbilled receivable
        public readonly Money $contractLiability,  // advance billing
    ) {}

    public function pocPercent(): string
    {
        return number_format($this->pocRatioPpm / 10_000, 2).'%';
    }
}
