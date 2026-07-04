<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

/**
 * The fact Payables publishes when a payment batch settles bills. Finance posts the
 * net cash movement (Dr AP / Cr Bank); the batch's per-bill allocation lives on the
 * batch, not in the GL.
 */
final class PaymentMadeFact
{
    public const TYPE = 'payables.payment_made';

    public function __construct(
        public readonly string $batchId,
        public readonly string $currency,
        public readonly int $amountMinor,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'batch_id' => $this->batchId,
            'currency' => $this->currency,
            'amount' => $this->amountMinor,
        ];
    }
}
