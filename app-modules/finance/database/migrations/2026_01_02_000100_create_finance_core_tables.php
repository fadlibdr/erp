<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The general-ledger core. Journals are append-only: there is no UPDATE or
 * DELETE path in the application, and corrections are posted as reversals. Every
 * journal line carries the project / WBS / cost-code dimensions so the entire
 * ledger is sliceable by project without a parallel cost system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 24);
            $table->string('name');
            $table->string('type', 16);                // asset|liability|equity|revenue|expense
            $table->uuid('parent_id')->nullable();
            $table->boolean('is_postable')->default(true); // leaf accounts only
            $table->string('currency', 3)->nullable();     // null = company base currency
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('fin_fiscal_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('label', 16);               // "2026-07"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 12)->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'label']);
        });

        Schema::create('fin_journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('number', 32)->nullable();
            $table->date('date');
            $table->string('description');
            $table->string('fact_type', 96)->nullable();   // the outbox fact that produced this
            $table->string('source_reference', 96)->nullable();
            $table->uuid('reverses_journal_id')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('total_minor');             // = sum of debits = sum of credits
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'date']);
            $table->index('fact_type');
        });

        Schema::create('fin_journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_id');
            $table->uuid('company_id');
            $table->string('account_code', 24);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->string('currency', 3);
            // project cost dimensions on every line
            $table->uuid('project_id')->nullable();
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->foreign('journal_id')->references('id')->on('fin_journals')->cascadeOnDelete();
            $table->index(['company_id', 'account_code']);
            $table->index('project_id');
        });

        // Per-customer posting rules: role -> account code. The rule *shape* is
        // code; the account mapping is data, so a different chart of accounts is
        // configuration, not a release.
        Schema::create('fin_posting_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('fact_type', 96);
            $table->string('role', 64);                // e.g. "accounts_receivable"
            $table->string('account_code', 24);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'fact_type', 'role']);
        });

        // Commitment ledger: PO commitments consumed as GRNs arrive, checked
        // against the project control budget (PO block/warn lives on top of this).
        Schema::create('fin_commitments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id');
            $table->uuid('wbs_id')->nullable();
            $table->string('cost_code', 32)->nullable();
            $table->string('source_type', 32);         // purchase_order | subcontract
            $table->uuid('source_id');
            $table->bigInteger('committed_minor');
            $table->bigInteger('consumed_minor')->default(0);
            $table->string('currency', 3);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['project_id', 'wbs_id']);
        });

        // PSAK 72 recognition runs — one per period per project, auditable.
        Schema::create('fin_revrec_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('project_id');
            $table->uuid('fiscal_period_id');
            $table->unsignedInteger('poc_ratio_ppm');  // percent-of-completion in parts-per-million (e.g. 42.5% = 425000)
            $table->bigInteger('recognized_to_date_minor');
            $table->bigInteger('billed_to_date_minor');
            $table->bigInteger('contract_asset_minor');    // unbilled receivable (if recognized > billed)
            $table->bigInteger('contract_liability_minor'); // advance billing (if billed > recognized)
            $table->uuid('journal_id')->nullable();
            $table->string('currency', 3);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['project_id', 'fiscal_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_revrec_runs');
        Schema::dropIfExists('fin_commitments');
        Schema::dropIfExists('fin_posting_rules');
        Schema::dropIfExists('fin_journal_lines');
        Schema::dropIfExists('fin_journals');
        Schema::dropIfExists('fin_fiscal_periods');
        Schema::dropIfExists('fin_accounts');
    }
};
