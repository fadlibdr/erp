<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

use RuntimeException;

/**
 * The lifecycle of one e-Faktur submission to Coretax.
 *
 *   Queued ─▶ Sent ─▶ Acked        (happy path: built, transmitted, NTTE/approval returned)
 *      │        │
 *      ▼        ▼
 *    Failed ◀───┘   ─▶ Sent        (a failure is retryable back to Sent)
 *
 * Acked is terminal — an approved tax invoice with its NSFP/approval code is final.
 * The guard exists so a race or a double-relay can never, say, mark an already-acked
 * invoice failed and re-file it (which would burn a second serial number).
 */
enum EfakturSubmissionStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Acked = 'acked';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Queued => [self::Sent, self::Failed],
            self::Sent => [self::Acked, self::Failed],
            self::Failed => [self::Sent],
            self::Acked => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function transitionTo(self $next): self
    {
        if (! $this->canTransitionTo($next)) {
            throw new RuntimeException("Illegal e-Faktur transition {$this->value} → {$next->value}.");
        }

        return $next;
    }

    public function isTerminal(): bool
    {
        return $this === self::Acked;
    }
}
