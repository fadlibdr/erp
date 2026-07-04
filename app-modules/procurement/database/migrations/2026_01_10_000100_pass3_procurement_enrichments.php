<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 3 enrichments for the commitment loop:
 *  - proc_grn_lines: the item-level detail of a goods receipt (Pass 1 shipped the
 *    GRN header only). Each line carries the item/warehouse it lands in and the
 *    WBS/cost-code the cost belongs to, so the receipt can both value inventory
 *    (moving average, per item×warehouse) and consume the right commitment bucket.
 *  - proc_purchase_orders.budget_status: records a soft budget WARN raised at
 *    approval, so the UI can surface an over-90% commitment for a buyer's attention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_grn_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grn_id');
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('item_id')->nullable();
            $table->uuid('warehouse_id')->nullable();
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->timestamps();
            $table->foreign('grn_id')->references('id')->on('proc_grns')->cascadeOnDelete();
            $table->index('purchase_order_line_id');
        });

        Schema::table('proc_purchase_orders', function (Blueprint $table) {
            $table->string('budget_status', 12)->default('ok'); // ok | warn
        });
    }

    public function down(): void
    {
        Schema::table('proc_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('budget_status');
        });
        Schema::dropIfExists('proc_grn_lines');
    }
};
