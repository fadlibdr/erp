<?php

declare(strict_types=1);

namespace Modules\Receivables\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\Action;
use Modules\Platform\Support\Outbox;
use Modules\Receivables\Events\ReceiptReceived;
use Modules\Receivables\Models\ArInvoice;
use Modules\Receivables\Models\ArReceipt;

/**
 * Records a customer cash receipt against an AR invoice, marks the invoice paid once
 * fully settled, and publishes the fact Finance posts as Dr Bank / Cr AR.
 */
final class ReceiveCustomerPayment extends Action
{
    public function __construct(
        private readonly Outbox $outbox,
    ) {}

    public function execute(ArInvoice $invoice, int $amountMinor, string $receiptDate): ArReceipt
    {
        return DB::transaction(function () use ($invoice, $amountMinor, $receiptDate): ArReceipt {
            $receipt = ArReceipt::create([
                'company_id' => $invoice->company_id,
                'project_id' => $invoice->project_id,
                'ar_invoice_id' => $invoice->id,
                'receipt_date' => $receiptDate,
                'amount_minor' => $amountMinor,
                'currency' => $invoice->currency,
            ]);

            $received = (int) ArReceipt::query()->where('ar_invoice_id', $invoice->id)->sum('amount_minor');
            if ($received >= (int) $invoice->net_minor) {
                $invoice->update(['status' => 'paid']);
            }

            $event = new ReceiptReceived($invoice->company_id, $receipt->id, $invoice->project_id, $invoice->currency, $amountMinor);
            $this->outbox->publish($event, $event->dedupKey());

            return $receipt;
        });
    }
}
