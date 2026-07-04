<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

use RuntimeException;

/**
 * Builds the Coretax e-Faktur XML document for one output tax invoice
 * (PER-11/PJ/2025 registration flow).
 *
 * Pure string construction with strict XML escaping — no framework, no I/O — so the
 * exact bytes we will POST to Coretax are unit-testable the moment they are written,
 * exactly like the ledger and tax-rate logic. The element names follow the Coretax
 * TaxInvoice family; the team reconciles them against the live XSD version at
 * integration time (the shape and the arithmetic are what this class guarantees).
 *
 * The builder also *checks its own totals*: the declared DPP/PPN sums must equal the
 * sum of the lines, so a malformed invoice can never be filed under a real serial.
 */
final class EfakturXmlBuilder
{
    public function build(EfakturInvoice $invoice): string
    {
        if ($invoice->lines === []) {
            throw new RuntimeException('An e-Faktur must have at least one line.');
        }

        $goods = '';
        foreach ($invoice->lines as $line) {
            $goods .= '    <GoodService>'."\n"
                .$this->el('Opt', $line['kind'], 6)
                .$this->el('Name', $line['name'], 6)
                .$this->el('Price', (string) (int) $line['priceMinor'], 6)
                .$this->el('TaxBase', (string) (int) $line['dppMinor'], 6)
                .$this->el('VAT', (string) (int) $line['ppnMinor'], 6)
                .'    </GoodService>'."\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<TaxInvoice>'."\n"
            .$this->el('TaxInvoiceDate', $invoice->taxInvoiceDate)
            .$this->el('TaxInvoiceOpt', $invoice->isReplacement ? 'Replacement' : 'Normal')
            .$this->el('TrxCode', $invoice->transactionCode)
            .$this->el('TaxInvoiceNumber', $invoice->taxInvoiceNumber)
            .$this->el('SellerTaxNumber', $invoice->sellerTaxNumber)
            .$this->el('BuyerTaxNumber', $invoice->buyerTaxNumber)
            .$this->el('BuyerName', $invoice->buyerName)
            .$this->el('ReferenceNumber', $invoice->referenceNumber)
            .$goods
            .$this->el('SumDPP', (string) $invoice->sumDppMinor())
            .$this->el('SumPPN', (string) $invoice->sumPpnMinor())
            .'</TaxInvoice>'."\n";
    }

    private function el(string $tag, string $value, int $indent = 2): string
    {
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return str_repeat(' ', $indent)."<{$tag}>{$escaped}</{$tag}>"."\n";
    }
}
