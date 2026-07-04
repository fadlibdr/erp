<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 5B: allocation lines for a payment batch — which approved bills a batch
 * settles, and for how much. ap_payment_batches shipped as a header only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_batch_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_batch_id');
            $table->uuid('vendor_bill_id');
            $table->bigInteger('amount_minor')->default(0);
            $table->timestamps();
            $table->foreign('payment_batch_id')->references('id')->on('ap_payment_batches')->cascadeOnDelete();
            $table->index('vendor_bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_batch_lines');
    }
};
