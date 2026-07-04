<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 5B: customer cash receipts against AR invoices — the settlement the Pass-1
 * receivables schema (invoices, retentions, advances) had no table for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('ar_invoice_id');
            $table->date('receipt_date');
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index('ar_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_receipts');
    }
};
