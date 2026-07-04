<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EPC spine. A project carries its contract commercials (value, retention %,
 * uang muka %, termin scheme). The WBS is a tree (adjacency + materialized path
 * for cheap subtree queries). The BOQ is versioned (tender -> contract -> variation
 * orders); each BOQ line hangs off a WBS node. The control budget (WBS x cost code)
 * is what procurement checks PO commitments against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prj_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->uuid('customer_id')->nullable();
            $table->string('contract_number', 64)->nullable();
            $table->date('contract_date')->nullable();     // drives the PPh-final regime (transitional rule)
            $table->string('service_class', 32)->default('integrated_work'); // EPC by default
            $table->bigInteger('contract_value_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->unsignedSmallInteger('retention_percent')->default(5);
            $table->unsignedSmallInteger('uang_muka_percent')->default(20);
            $table->string('status', 16)->default('active'); // planning|active|maintenance|closed
            $table->date('pho_date')->nullable();           // BAST-I / provisional hand over
            $table->date('fho_date')->nullable();           // BAST-II / final hand over
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('prj_wbs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 48);                     // "1.2.3"
            $table->string('path', 255);                    // materialized path for subtree queries
            $table->string('name');
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedBigInteger('weight_ppm')->default(0); // schedule weight, parts-per-million
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('prj_projects')->cascadeOnDelete();
            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'path']);
        });

        Schema::create('prj_boq_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('label', 32);                    // "tender", "contract", "VO-01"
            $table->unsignedInteger('revision')->default(0);
            $table->string('status', 16)->default('draft'); // draft | locked
            $table->bigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('prj_projects')->cascadeOnDelete();
            $table->unique(['project_id', 'label', 'revision']);
        });

        Schema::create('prj_boq_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('boq_version_id');
            $table->uuid('project_id');
            $table->uuid('wbs_id')->nullable();
            $table->string('item_code', 48)->nullable();
            $table->string('description');
            $table->string('unit', 16);                     // m3, m2, ls, ton
            $table->decimal('quantity', 18, 3)->default(0);
            $table->bigInteger('unit_rate_minor')->default(0);
            $table->bigInteger('amount_minor')->default(0); // quantity * unit_rate (stored, checked)
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign('boq_version_id')->references('id')->on('prj_boq_versions')->cascadeOnDelete();
            $table->index(['project_id', 'wbs_id']);
        });

        // Control budget: the locked spending ceiling per WBS x cost code that
        // procurement commitments are validated against.
        Schema::create('prj_budget_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('wbs_id');
            $table->string('cost_code', 32);                // MAT | SUB | LAB | EQP | OVH
            $table->bigInteger('budget_minor');
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('prj_projects')->cascadeOnDelete();
            $table->unique(['project_id', 'wbs_id', 'cost_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_budget_lines');
        Schema::dropIfExists('prj_boq_lines');
        Schema::dropIfExists('prj_boq_versions');
        Schema::dropIfExists('prj_wbs');
        Schema::dropIfExists('prj_projects');
    }
};
