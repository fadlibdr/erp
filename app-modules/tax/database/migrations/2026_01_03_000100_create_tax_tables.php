<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory tax data. Rates are effective-dated rows — never constants in code —
 * so a new PP is onboarded by inserting rows with a new effective window. The
 * e-Faktur submission table is an outbox: each invoice's clearance is a row with
 * a status machine, so DJP/Coretax availability never blocks invoicing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PPh final konstruksi rates (PP 9/2022, and PP 51/2008 for the
        // transitional rule). rate_numerator is over a fixed denominator of 10,000.
        Schema::create('tax_pph_final_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service_class', 32);       // construction_work | integrated_work | consulting
            $table->string('sbu_class', 32);           // small | medium_large_spec | none
            $table->unsignedInteger('rate_numerator'); // 265 == 2.65%
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('regulation_ref', 64);
            $table->timestamps();

            $table->index(['service_class', 'sbu_class', 'effective_from']);
        });

        // e-Faktur submission outbox — one row per tax invoice.
        Schema::create('tax_efaktur_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('source_type', 32);         // sales_invoice | progress_claim
            $table->uuid('source_id');
            $table->string('status', 12)->default('queued'); // queued | sent | failed | acked
            $table->string('channel', 16)->nullable();       // file | coretax_api | pjap
            $table->string('dedup_key', 128)->nullable();
            $table->string('nsfp', 32)->nullable();          // approved tax invoice serial
            $table->string('approval_code', 64)->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique('dedup_key');
            $table->index(['status', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_efaktur_submissions');
        Schema::dropIfExists('tax_pph_final_rates');
    }
};
