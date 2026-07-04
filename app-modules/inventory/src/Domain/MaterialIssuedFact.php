<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain;

/**
 * The fact Inventory publishes when material is issued to a project. The stock
 * movements are written in-module (Inventory owns the stock ledger); this fact
 * carries only the total value + the project/WBS/cost-code dimensions Finance needs
 * to post Dr project material cost / Cr inventory.
 */
final class MaterialIssuedFact
{
    public const TYPE = 'inventory.material_issued';

    public function __construct(
        public readonly string $issueId,
        public readonly string $projectId,
        public readonly ?string $wbsId,
        public readonly string $costCode,
        public readonly string $currency,
        public readonly int $amountMinor,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'issue_id' => $this->issueId,
            'project_id' => $this->projectId,
            'wbs_id' => $this->wbsId,
            'cost_code' => $this->costCode,
            'currency' => $this->currency,
            'amount' => $this->amountMinor,
        ];
    }
}
