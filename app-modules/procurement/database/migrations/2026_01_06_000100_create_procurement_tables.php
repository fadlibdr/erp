<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement: vendors (carrying the SBU class that Tax reads for sub PPh), POs
 * that raise a commitment against the project control budget, and GRNs that
 * consume the commitment and emit the fact Finance posts as an accrual.
 * Pass 1 lays the schema; the PR/RFQ flow and commitment enforcement land in Pass 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->string('npwp', 32)->nullable();
            $table->string('sbu_class', 32)->nullable();  // drives PPh withholding for subcontractors
            $table->boolean('is_pkp')->default(false);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('proc_purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('vendor_id');
            $table->string('number', 32)->nullable();
            $table->date('po_date');
            $table->string('status', 16)->default('draft'); // draft|approved|received|closed
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });

        Schema::create('proc_purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->nullable();
            $table->string('description');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->bigInteger('unit_rate_minor')->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->timestamps();
            $table->foreign('purchase_order_id')->references('id')->on('proc_purchase_orders')->cascadeOnDelete();
        });

        Schema::create('proc_grns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('purchase_order_id');
            $table->string('number', 32)->nullable();
            $table->date('received_date');
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('purchase_order_id')->references('id')->on('proc_purchase_orders')->cascadeOnDelete();
        });

        Schema::create('proc_tkdn_declarations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->string('item_ref', 96)->nullable();
            $table->unsignedInteger('tkdn_percent_bp')->default(0); // basis points (7550 = 75.50%)
            $table->bigInteger('local_value_minor')->default(0);
            $table->bigInteger('import_value_minor')->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_tkdn_declarations');
        Schema::dropIfExists('proc_grns');
        Schema::dropIfExists('proc_purchase_order_lines');
        Schema::dropIfExists('proc_purchase_orders');
        Schema::dropIfExists('proc_vendors');
    }
};
