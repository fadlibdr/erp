<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Domain\Account\AccountType;
use Modules\Finance\Models\Account;

/**
 * Seeds a starter Indonesian construction chart of accounts and the posting-rule
 * account mappings a company needs before it can post. This is deliberately data,
 * not code: a customer with a different CoA edits fin_posting_rules, not classes.
 */
final class ConstructionLedgerSeeder
{
    /** account code => [name, type] */
    private const ACCOUNTS = [
        '1102' => ['Piutang Usaha', AccountType::Asset],
        '1103' => ['Piutang Retensi', AccountType::Asset],
        '1181' => ['PPh Final Dibayar Dimuka', AccountType::Asset],
        '2103' => ['Uang Muka Diterima', AccountType::Liability],
        '2151' => ['PPN Keluaran', AccountType::Liability],
        '4101' => ['Pendapatan Kontrak', AccountType::Revenue],
        '5101' => ['Beban Pokok Kontrak', AccountType::Expense],
        '1301' => ['Persediaan Proyek', AccountType::Asset],
        '2101' => ['Utang Usaha', AccountType::Liability],
        '1152' => ['PPN Masukan', AccountType::Asset],
        '2104' => ['Utang Retensi', AccountType::Liability],
        '2131' => ['Utang PPh Final Konstruksi', AccountType::Liability],
        // Pass 3: commitment loop (GR/IR) and PSAK 72 month-end recognition.
        '2109' => ['Akrual Penerimaan Barang (GR/IR)', AccountType::Liability],
        '1171' => ['Aset Kontrak (Pendapatan Belum Ditagih)', AccountType::Asset],
        '2181' => ['Liabilitas Kontrak (Tagihan Diterima Dimuka)', AccountType::Liability],
        // Pass 5: cash/bank (settlement + payroll), material cost, payroll payables.
        '1101' => ['Kas & Bank', AccountType::Asset],
        '5102' => ['Beban Material Proyek', AccountType::Expense],
    ];

    /** posting role => account code, for the progress-invoice fact */
    private const PROGRESS_INVOICE_ROLES = [
        'accounts_receivable' => '1102',
        'retention_receivable' => '1103',
        'advance_liability' => '2103',
        'pph_final_prepaid' => '1181',
        'contract_revenue' => '4101',
        'ppn_output' => '2151',
    ];

    /** posting role => account code, for the subcontractor-bill fact (the P2P mirror) */
    private const VENDOR_BILL_ROLES = [
        'subcontract_cost' => '5101',
        'ppn_input' => '1152',
        'accounts_payable' => '2101',
        'retention_payable' => '2104',
        'pph_final_payable' => '2131',
    ];

    /** posting role => account code, for the goods-received fact (GR/IR accrual) */
    private const GRN_ROLES = [
        'inventory_wip' => '1301',
        'gr_ir_accrual' => '2109',
    ];

    /** posting role => account code, for the PSAK 72 month-end recognition true-up */
    private const REVENUE_RECOGNITION_ROLES = [
        'contract_asset' => '1171',
        'contract_liability' => '2181',
        'contract_revenue' => '4101',
    ];

    /** posting role => account code, for the PO-linked material bill (clears GR/IR) */
    private const MATERIAL_BILL_ROLES = [
        'gr_ir_accrual' => '2109',
        'ppn_input' => '1152',
        'accounts_payable' => '2101',
        'retention_payable' => '2104',
    ];

    /** posting role => account code, for a material issue → project cost (Pass 5A) */
    private const MATERIAL_ISSUE_ROLES = [
        'project_material_cost' => '5102',
        'inventory' => '1301',
    ];

    public function seedForCompany(string $companyId): void
    {
        foreach (self::ACCOUNTS as $code => [$name, $type]) {
            Account::updateOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'type' => $type->value, 'is_postable' => true, 'active' => true],
            );
        }

        $this->seedRoles($companyId, 'billing.progress_invoice_issued', self::PROGRESS_INVOICE_ROLES);
        $this->seedRoles($companyId, 'payables.vendor_bill_approved', self::VENDOR_BILL_ROLES);
        $this->seedRoles($companyId, 'procurement.goods_received', self::GRN_ROLES);
        $this->seedRoles($companyId, 'finance.revenue_recognized', self::REVENUE_RECOGNITION_ROLES);
        $this->seedRoles($companyId, 'payables.material_bill_approved', self::MATERIAL_BILL_ROLES);
        $this->seedRoles($companyId, 'inventory.material_issued', self::MATERIAL_ISSUE_ROLES);
    }

    /**
     * @param  array<string, string>  $roles  posting role => account code
     */
    private function seedRoles(string $companyId, string $factType, array $roles): void
    {
        foreach ($roles as $role => $code) {
            DB::table('fin_posting_rules')->updateOrInsert(
                ['company_id' => $companyId, 'fact_type' => $factType, 'role' => $role],
                ['id' => (string) Str::uuid(), 'account_code' => $code, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
