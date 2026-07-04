<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 5: item-level detail for a material issue (inv_issues shipped header-only).
 * Each line is one item leaving a warehouse for a WBS/cost code — the grain the
 * moving-average valuation and the project-cost posting both need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_issue_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_issue_id');
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->bigInteger('amount_minor')->default(0); // moving-average value of the issue
            $table->timestamps();
            $table->foreign('material_issue_id')->references('id')->on('inv_issues')->cascadeOnDelete();
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_issue_lines');
    }
};
