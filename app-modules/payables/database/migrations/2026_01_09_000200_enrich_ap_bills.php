<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 2 enriches ap_bills into the payables mirror of a termin claim: it carries
 * the fully-decomposed figures a subcontractor bill withholds, so the bill, its
 * journal, and the PPh withholding certificate all read from one frozen row.
 *
 * The Pass-1 table only stored the net side (gross / pph_withheld / retention /
 * net_payable). To post a balanced accrual we also need the DPP (work value) and
 * the creditable input VAT split out, plus the provenance of the withholding rate
 * and the bill's own service class / retention terms (a subcontract's terms are
 * its own, not the head contract's).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ap_bills', function (Blueprint $table) {
            $table->bigInteger('work_value_minor')->default(0)->after('bill_date'); // DPP / accrued cost
            $table->bigInteger('ppn_input_minor')->default(0)->after('work_value_minor'); // creditable PPN Masukan
            $table->string('service_class', 32)->default('construction_work')->after('ppn_input_minor');
            // The subcontract's own signing date drives the PPh regime (transitional
            // rule) — a subcontract carries its own terms, not the head contract's.
            $table->date('contract_date')->nullable()->after('service_class');
            $table->unsignedSmallInteger('retention_percent')->default(0)->after('contract_date');
            $table->string('cost_code', 32)->default('SUB')->after('retention_percent');
            $table->uuid('wbs_id')->nullable()->after('cost_code');
            $table->unsignedInteger('pph_rate_numerator')->default(0)->after('wbs_id'); // over 10_000
            $table->string('pph_regulation_ref', 64)->nullable()->after('pph_rate_numerator');
        });
    }

    public function down(): void
    {
        Schema::table('ap_bills', function (Blueprint $table) {
            $table->dropColumn([
                'work_value_minor', 'ppn_input_minor', 'service_class', 'contract_date', 'retention_percent',
                'cost_code', 'wbs_id', 'pph_rate_numerator', 'pph_regulation_ref',
            ]);
        });
    }
};
