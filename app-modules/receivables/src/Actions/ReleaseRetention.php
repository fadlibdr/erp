<?php

declare(strict_types=1);

namespace Modules\Receivables\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\Action;
use Modules\Platform\Support\Outbox;
use Modules\Receivables\Events\RetentionReleased;
use Modules\Receivables\Models\ArRetention;
use RuntimeException;

/**
 * Releases a held retention at final hand-over (FHO / BAST-II): flips the sub-ledger
 * to released and publishes the fact Finance posts as Dr Bank / Cr Retention Receivable.
 */
final class ReleaseRetention extends Action
{
    public function __construct(
        private readonly Outbox $outbox,
    ) {}

    public function execute(ArRetention $retention, string $releaseDate): ArRetention
    {
        if ($retention->status === 'released') {
            throw new RuntimeException("Retention {$retention->id} is already released.");
        }

        return DB::transaction(function () use ($retention, $releaseDate): ArRetention {
            $retention->update(['status' => 'released', 'released_at' => $releaseDate]);

            $event = new RetentionReleased(
                $retention->company_id,
                $retention->id,
                $retention->project_id,
                $retention->currency,
                (int) $retention->amount_minor,
            );
            $this->outbox->publish($event, $event->dedupKey());

            return $retention;
        });
    }
}
