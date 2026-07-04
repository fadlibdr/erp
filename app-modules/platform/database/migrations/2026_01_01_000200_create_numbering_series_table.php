<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap-free document numbering per company + series. The `next` counter is bumped
 * under a row lock (SELECT ... FOR UPDATE) by NumberingService, which is what
 * keeps two concurrent invoices from ever taking the same nomor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('key', 64);                 // e.g. "sales_invoice", "progress_claim"
            $table->string('format', 128);             // e.g. "INV-{YYYY}-{####}"
            $table->unsignedBigInteger('next')->default(1);
            $table->string('period_scope', 8)->default('year'); // year | month | none — when the counter resets
            $table->string('current_period', 16)->nullable();   // the period the counter belongs to
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_series');
    }
};
