<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 4: records the three-way-match verdict (PO ↔ GRN ↔ bill) on a material bill,
 * so a clean/variance outcome is auditable and can badge in the UI. The PO link
 * itself (`purchase_order_id`) already exists from the Pass-1 ap_bills schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ap_bills', function (Blueprint $table) {
            $table->string('match_status', 24)->nullable()->after('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('ap_bills', function (Blueprint $table) {
            $table->dropColumn('match_status');
        });
    }
};
