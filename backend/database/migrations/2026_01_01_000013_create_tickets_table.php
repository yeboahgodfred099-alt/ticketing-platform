<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->string('ticket_number', 100)->unique();
            $table->string('qr_token_hash', 255)->unique();
            $table->string('holder_name', 255);
            $table->string('holder_email', 255)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->timestampTz('issued_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

