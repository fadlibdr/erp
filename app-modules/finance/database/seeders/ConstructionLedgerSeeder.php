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
