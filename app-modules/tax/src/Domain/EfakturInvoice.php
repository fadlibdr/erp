<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * The input to the e-Faktur XML builder: everything Coretax needs to register one
 * output tax invoice, decoupled from how it is stored. Amounts are whole rupiah
 * (Money minor units for IDR), matching e-Faktur, which carries no sen.
 *
 * @phpstan-type EfakturLine array{name: string, kind: string, priceMinor: int, dppMinor: int, ppnMinor: int}
 */
final class EfakturInvoice
{
    /**
     * @param  list<EfakturLine>  $lines  each: name, kind ('B' goods / 'J' service), price, DPP, PPN
     */
    public function __construct(
        public readonly string $sellerTaxNumber,
        public readonly string $buyerTaxNumber,
        public readonly string $buyerName,
        public readonly string $taxInvoiceNumber,   // NSFP serial
        public readonly string $taxInvoiceDate,     // YYYY-MM-DD
        public readonly string $transactionCode,    // KD_JENIS_TRANSAKSI, e.g. "04"
        public readonly bool $isReplacement,
        public readonly string $referenceNumber,    // source claim/invoice reference
        public readonly array $lines,
    ) {}

    public function sumDppMinor(): int
    {
        return array_sum(array_column($this->lines, 'dppMinor'));
    }

    public function sumPpnMinor(): int
    {
        return array_sum(array_column($this->lines, 'ppnMinor'));
    }
}
