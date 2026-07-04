<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll: employees (with the PTKP status that drives PPh 21), monthly pay runs
 * charged to a project/WBS, and per-employee run lines carrying the decomposed
 * figures (gross, PPh 21, BPJS split, net). The run posts labor cost to the GL via
 * the outbox, exactly like every other money path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->string('npwp', 32)->nullable();
            $table->string('ptkp_status', 8)->default('TK/0'); // TK/0..TK/3, K/0..K/3
            $table->bigInteger('monthly_gross_minor')->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('pay_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->default('LAB');
            $table->string('period', 7);                     // "2026-07"
            $table->string('status', 16)->default('draft');  // draft | approved
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('pph21_minor')->default(0);
            $table->bigInteger('bpjs_employee_minor')->default(0);
            $table->bigInteger('bpjs_employer_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'period']);
        });

        Schema::create('pay_run_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pay_run_id');
            $table->uuid('employee_id');
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('pph21_minor')->default(0);
            $table->bigInteger('bpjs_employee_minor')->default(0);
            $table->bigInteger('bpjs_employer_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->timestamps();
            $table->foreign('pay_run_id')->references('id')->on('pay_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_run_lines');
        Schema::dropIfExists('pay_runs');
        Schema::dropIfExists('pay_employees');
    }
};
