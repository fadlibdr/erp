<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Billing\Actions\IssueTerminInvoice;
use Modules\Billing\Models\ProgressClaim;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payables\Actions\ApproveSubcontractorBill;
use Modules\Payables\Actions\PayVendorBills;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Models\Company;
use Modules\Procurement\Actions\ApprovePurchaseOrder;
use Modules\Procurement\Actions\ReceiveGoods;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Wbs;

/**
 * Populates the demo company with a live money trail so the panel isn't empty:
 * a project + control budget, an approved+paid subcontract bill (→ Lunas stamp),
 * an issued termin (→ Disetujui stamp), and a PO+GRN (→ committed/actual in the
 * budget widget). Idempotent-ish: skips if the demo project already exists.
 */
final class DemoTransactionsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'KON')->firstOrFail();
        if (Project::query()->where('company_id', $company->id)->where('code', 'PDC')->exists()) {
            return;
        }
        $relay = app(OutboxRelay::class);

        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PDC', 'name' => 'PDC Tower — Sistem Keamanan Elektronik',
            'contract_number' => '012/SPK/PII/VII/2026', 'contract_date' => '2026-03-01',
            'service_class' => 'integrated_work', 'contract_value_minor' => 4_850_000_000,
            'currency' => 'IDR', 'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
        ]);
        $wbs = Wbs::create([
            'project_id' => $project->id, 'code' => '1', 'path' => '1', 'name' => 'Instalasi CCTV', 'depth' => 0, 'weight_ppm' => 0,
        ]);
        BudgetLine::create([
            'project_id' => $project->id, 'wbs_id' => $wbs->id, 'cost_code' => 'MAT',
            'budget_minor' => 1_200_000_000, 'currency' => 'IDR',
        ]);

        $vendor = Vendor::create([
            'company_id' => $company->id, 'code' => 'SUB-01', 'name' => 'CV Instalasi Andal',
            'npwp' => '02.345.678.9-012.000', 'sbu_class' => 'small', 'is_pkp' => true,
        ]);

        // Subcontract bill → approve → pay ⇒ shows the "Lunas" stamp.
        $bill = VendorBill::create([
            'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
            'bill_date' => '2026-06-20', 'contract_date' => '2026-03-01', 'status' => 'draft',
            'service_class' => 'construction_work', 'retention_percent' => 5, 'cost_code' => 'SUB',
            'work_value_minor' => 180_000_000, 'currency' => 'IDR',
        ]);
        app(ApproveSubcontractorBill::class)->execute($bill);
        $relay->drain();
        app(PayVendorBills::class)->execute($company->id, [$bill->id], '2026-06-30');
        $relay->drain();

        // An unpaid, approved subcontract bill (workflow "approved", no stamp yet).
        $bill2 = VendorBill::create([
            'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
            'bill_date' => '2026-07-02', 'contract_date' => '2026-03-01', 'status' => 'draft',
            'service_class' => 'construction_work', 'retention_percent' => 5, 'cost_code' => 'SUB',
            'work_value_minor' => 95_000_000, 'currency' => 'IDR',
        ]);
        app(ApproveSubcontractorBill::class)->execute($bill2);
        $relay->drain();

        // Progress claim → issue termin ⇒ shows the "Disetujui" stamp + AR sub-ledger.
        $claim = ProgressClaim::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'sequence' => 1,
            'claim_date' => '2026-06-25', 'status' => 'bapp', 'work_value_minor' => 1_212_500_000, 'currency' => 'IDR',
        ]);
        app(IssueTerminInvoice::class)->execute($claim);
        $relay->drain();

        // PO → approve (commitment) → receive goods (actual) ⇒ budget widget fills.
        $po = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'vendor_id' => $vendor->id,
            'po_date' => '2026-06-10', 'status' => 'draft', 'total_minor' => 620_000_000, 'currency' => 'IDR',
        ]);
        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'wbs_id' => $wbs->id, 'cost_code' => 'MAT',
            'description' => 'Kamera & DVR', 'quantity' => 40, 'unit_rate_minor' => 15_500_000, 'amount_minor' => 620_000_000,
        ]);
        app(ApprovePurchaseOrder::class)->execute($po);
        $relay->drain();
        app(ReceiveGoods::class)->execute($po->refresh(), [[
            'purchase_order_line_id' => $line->id, 'item_id' => (string) Str::uuid(),
            'warehouse_id' => (string) Str::uuid(), 'qty_milli' => 40_000, 'amount_minor' => 620_000_000,
        ]], '2026-06-18');
        $relay->drain();
    }
}
