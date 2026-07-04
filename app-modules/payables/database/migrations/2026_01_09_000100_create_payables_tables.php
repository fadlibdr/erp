<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payables: vendor bills (3-way match against PO + GRN in Pass 2) and payment
 * batches producing bank-transfer files. Subcontractor bills flow here from the
 * Subcontract module in Phase 2, carrying their own retention payable and PPh
 * withholding. Pass 1 schema only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->string('number', 32)->nullable();
            $table->date('bill_date');
            $table->string('status', 16)->default('open'); // open | paid | void
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('pph_withheld_minor')->default(0);
            $table->bigInteger('retention_minor')->default(0);
            $table->bigInteger('net_payable_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('ap_payment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('number', 32)->nullable();
            $table->date('payment_date');
            $table->string('bank', 16)->nullable();        // bca | mandiri | ...
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_batches');
        Schema::dropIfExists('ap_bills');
    }
};
