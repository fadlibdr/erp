<?php

declare(strict_types=1);

namespace Modules\Finance\Domain;

/**
 * The outcome of a control-budget check. OK proceeds silently; WARN proceeds but
 * flags the document for an approver's attention; BLOCK stops the commitment cold
 * (only a budget revision or an explicit override — a Pass 4 concern — lets it through).
 */
enum BudgetVerdict: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Block = 'block';

    public function isBlocking(): bool
    {
        return $this === self::Block;
    }
}
