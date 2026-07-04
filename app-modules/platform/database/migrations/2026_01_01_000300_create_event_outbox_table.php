<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transactional outbox. A domain event row is inserted in the same DB
 * transaction as the aggregate change; a worker then relays unprocessed rows to
 * handlers and stamps processed_at. `dedup_key` makes relaying idempotent so a
 * retried delivery never double-posts a journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('type', 96);                 // e.g. "billing.progress_invoice_issued"
            $table->jsonb('payload');
            $table->string('dedup_key', 128)->nullable();
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['processed_at', 'available_at']);   // the worker's claim query
            $table->unique('dedup_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_outbox');
    }
};
