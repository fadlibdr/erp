<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The disciplined metadata layer. Admins add custom fields against an entity;
 * values live in that entity's `custom_fields` JSONB column and are auto-rendered
 * in Filament. This is the *bounded* slice of ERPNext's DocType idea we keep —
 * fields, not runtime document types. Heavily-used fields get promoted to real
 * typed columns in an ordinary release (tracked via `promoted_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_defs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('entity', 96);              // e.g. "projects.project"
            $table->string('key', 64);                 // stored under custom_fields->key
            $table->string('label');
            $table->string('type', 24);                // text | number | date | select | boolean
            $table->jsonb('options')->nullable();      // for select
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'entity', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_defs');
    }
};
