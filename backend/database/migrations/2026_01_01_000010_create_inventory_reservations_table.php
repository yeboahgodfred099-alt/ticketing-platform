<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 30)->default('active')->index();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('converted_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();

            $table->unique(['order_id', 'ticket_type_id']);
            $table->index(['status', 'expires_at', 'ticket_type_id']);
        });

        DB::statement('ALTER TABLE inventory_reservations ADD CONSTRAINT check_reservation_quantity CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};

