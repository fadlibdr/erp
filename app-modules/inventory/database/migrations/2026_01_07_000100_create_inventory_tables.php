<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory: site warehouses (gudang proyek), a moving-average stock ledger
 * (append-only, like the GL), and material issues to a WBS/cost code — the issue
 * posting IS the actual material cost that flows to Finance. Pass 1 schema only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 48);
            $table->string('name');
            $table->string('unit', 16);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('inv_warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id')->nullable();       // null = central; set = gudang proyek
            $table->string('code', 32);
            $table->string('name');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('inv_stock_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->string('movement_type', 16);          // grn | issue | transfer | opname
            $table->uuid('source_id')->nullable();
            $table->decimal('qty_delta', 18, 3);          // + in, - out
            $table->bigInteger('value_delta_minor');      // moving-average valuation
            $table->decimal('balance_qty', 18, 3);
            $table->bigInteger('balance_value_minor');
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['item_id', 'warehouse_id', 'posted_at']);
        });

        Schema::create('inv_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id');
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->default('MAT');
            $table->uuid('warehouse_id');
            $table->date('issue_date');
            $table->bigInteger('total_minor')->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'wbs_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_issues');
        Schema::dropIfExists('inv_stock_ledger');
        Schema::dropIfExists('inv_warehouses');
        Schema::dropIfExists('inv_items');
    }
};
