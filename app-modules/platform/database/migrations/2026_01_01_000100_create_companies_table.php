<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies are the multi-tenant root and, crucially, the KSO substrate: a joint
 * operation (KSO) is simply a Company with its own NPWP and its own books
 * (PMK 79/2024). Everything financial is scoped by company_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('npwp', 32)->nullable();       // tax id
            $table->boolean('is_pkp')->default(true);     // VAT-registered
            $table->string('base_currency', 3)->default('IDR');

            // KSO fields — null for an ordinary operating company.
            $table->boolean('is_kso')->default(false);
            $table->uuid('kso_lead_company_id')->nullable();

            // Construction licensing (SBU) at the company level; expiry drives alerts.
            $table->string('sbu_class', 32)->nullable();   // small | medium_large_spec | none
            $table->date('sbu_valid_until')->nullable();

            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_kso');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 32);
            $table->string('name');
            $table->string('type', 24)->default('office'); // office | project_site | warehouse
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
