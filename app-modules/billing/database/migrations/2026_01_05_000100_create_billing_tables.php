<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress billing. A claim certifies work per BOQ line (prev-cum / this-period /
 * cum quantities), passes through the Berita Acara chain (BA Opname -> BAPP),
 * and on approval produces the termin invoice whose decomposed figures are the
 * ProgressInvoiceFact posted to the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bil_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id');
            $table->string('number', 32)->nullable();
            $table->unsignedInteger('sequence')->default(1); // termin number
            $table->date('claim_date');
            $table->string('status', 16)->default('draft');  // draft|ba_opname|bapp|approved|invoiced
            $table->bigInteger('work_value_minor')->default(0);
            // decomposed termin figures, frozen at approval for audit
            $table->bigInteger('ppn_output_minor')->default(0);
            $table->bigInteger('retention_minor')->default(0);
            $table->bigInteger('uang_muka_recovery_minor')->default(0);
            $table->bigInteger('pph_final_minor')->default(0);
            $table->bigInteger('net_receivable_minor')->default(0);
            $table->unsignedInteger('pph_rate_numerator')->nullable();
            $table->string('pph_regulation_ref', 64)->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('prj_projects')->cascadeOnDelete();
            $table->unique(['company_id', 'number']);
            $table->index(['project_id', 'sequence']);
        });

        Schema::create('bil_claim_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('claim_id');
            $table->uuid('boq_line_id')->nullable();
            $table->uuid('wbs_id')->nullable();
            $table->decimal('prev_cum_qty', 18, 3)->default(0);
            $table->decimal('this_qty', 18, 3)->default(0);
            $table->decimal('cum_qty', 18, 3)->default(0);
            $table->bigInteger('unit_rate_minor')->default(0);
            $table->bigInteger('this_value_minor')->default(0);
            $table->timestamps();

            $table->foreign('claim_id')->references('id')->on('bil_claims')->cascadeOnDelete();
        });

        Schema::create('bil_berita_acara', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('claim_id');
            $table->string('type', 16);                      // ba_opname | bapp | bast_1 | bast_2
            $table->string('number', 48)->nullable();
            $table->date('document_date');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('claim_id')->references('id')->on('bil_claims')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bil_berita_acara');
        Schema::dropIfExists('bil_claim_lines');
        Schema::dropIfExists('bil_claims');
    }
};
