<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 30)->default('paystack');
            $table->string('event_identifier', 255);
            $table->string('event_type', 100);
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('payload_hash', 128);
            $table->string('processing_status', 30)->default('received')->index();
            $table->timestampTz('received_at')->index();
            $table->timestampTz('processed_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'event_identifier']);
            $table->index(['processing_status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};

