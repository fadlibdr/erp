<?php

declare(strict_types=1);

namespace Modules\Payables\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payables\Domain\PaymentMadeFact;
use Modules\Payables\Events\PaymentMade;
use Modules\Payables\Models\PaymentBatch;
use Modules\Payables\Models\PaymentBatchLine;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Actions\Action;
use Modules\Platform\Support\Outbox;
use RuntimeException;

/**
 * Settles a set of approved vendor bills in one payment batch. It allocates each
 * bill's net payable to a batch line, marks the bills paid, and publishes the fact
 * Finance posts as Dr Accounts Payable / Cr Bank. The batch's per-bill allocation
 * stays on the batch; only the net cash movement reaches the GL.
 */
final class PayVendorBills extends Action
{
    public function __construct(
        private readonly Outbox $outbox,
    ) {}

    /**
     * @param  list<string>  $billIds
     */
    public function execute(string $companyId, array $billIds, string $paymentDate, string $bank = 'bca'): PaymentBatch
    {
        if ($billIds === []) {
            throw new RuntimeException('A payment batch needs at least one bill.');
        }

        $bills = VendorBill::query()->whereIn('id', $billIds)->where('company_id', $companyId)->get();

        foreach ($bills as $bill) {
            if ($bill->status !== 'approved') {
                throw new RuntimeException("Bill {$bill->id} is not approved (status {$bill->status}); cannot pay it.");
            }
        }

        $currency = $bills->first()?->currency ?? 'IDR';
        $total = (int) $bills->sum('net_payable_minor');

        return DB::transaction(function () use ($companyId, $bills, $paymentDate, $bank, $currency, $total): PaymentBatch {
            $batch = PaymentBatch::create([
                'company_id' => $companyId,
                'payment_date' => $paymentDate,
                'bank' => $bank,
                'total_minor' => $total,
                'currency' => $currency,
            ]);

            foreach ($bills as $bill) {
                PaymentBatchLine::create([
                    'payment_batch_id' => $batch->id,
                    'vendor_bill_id' => $bill->id,
                    'amount_minor' => (int) $bill->net_payable_minor,
                ]);
                $bill->update(['status' => 'paid']);
            }

            $fact = new PaymentMadeFact($batch->id, $currency, $total);
            $event = new PaymentMade($companyId, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $batch;
        });
    }
}
