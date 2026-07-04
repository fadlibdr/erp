<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receivables: customer invoices (born from Billing events), the retention
 * receivable sub-ledger (released on BAST), and uang muka received + its
 * amortization. The retention and advance sub-ledgers are the features Accurate
 * users work around today. Pass 1 schema only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->uuid('source_claim_id')->nullable();  // the progress claim it came from
            $table->string('number', 32)->nullable();
            $table->date('invoice_date');
            $table->string('status', 16)->default('open'); // open | paid | void
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('ar_retentions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id');
            $table->uuid('source_claim_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('IDR');
            $table->string('status', 16)->default('held'); // held | released
            $table->date('expected_release_date')->nullable(); // tied to FHO / BAST-II
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('ar_advances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id');
            $table->bigInteger('received_minor');
            $table->bigInteger('recovered_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_advances');
        Schema::dropIfExists('ar_retentions');
        Schema::dropIfExists('ar_invoices');
    }
};
