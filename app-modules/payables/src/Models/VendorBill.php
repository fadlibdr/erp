<?php

declare(strict_types=1);

namespace Modules\Payables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A vendor / subcontractor bill — the payables mirror of a ProgressClaim. Created
 * "draft" with just the certified work value and its terms; ApproveSubcontractorBill
 * freezes the decomposed figures (PPN input, retensi held, PPh final withheld, net
 * payable) and marks it "approved", publishing the fact Finance posts as an accrual.
 *
 * @property string $id
 * @property string $company_id
 * @property string $vendor_id
 * @property string|null $project_id
 * @property string|null $number
 * @property Carbon $bill_date
 * @property Carbon|null $contract_date
 * @property string $status
 * @property int $work_value_minor
 * @property int $ppn_input_minor
 * @property string $currency
 * @property string $service_class
 * @property int $retention_percent
 * @property string|null $cost_code
 * @property string|null $wbs_id
 */
final class VendorBill extends Model
{
    use HasUuids;

    protected $table = 'ap_bills';

    protected $fillable = [
        'company_id', 'vendor_id', 'project_id', 'purchase_order_id', 'number', 'bill_date', 'status',
        'work_value_minor', 'ppn_input_minor', 'service_class', 'contract_date', 'retention_percent', 'cost_code', 'wbs_id',
        'gross_minor', 'pph_withheld_minor', 'retention_minor', 'net_payable_minor',
        'pph_rate_numerator', 'pph_regulation_ref', 'currency',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'contract_date' => 'date',
        'work_value_minor' => 'integer',
        'ppn_input_minor' => 'integer',
        'retention_percent' => 'integer',
        'gross_minor' => 'integer',
        'pph_withheld_minor' => 'integer',
        'retention_minor' => 'integer',
        'net_payable_minor' => 'integer',
        'pph_rate_numerator' => 'integer',
    ];
}
