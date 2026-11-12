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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor'); // in pesewas
            $table->char('currency', 3)->default('GHS');
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->nullable();
            $table->timestampTz('sales_starts_at')->nullable();
            $table->timestampTz('sales_ends_at')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestampsTz();

            $table->index(['event_id', 'status']);
        });

        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT check_ticket_price_positive CHECK (price_minor >= 0)');
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT check_ticket_min_order CHECK (min_per_order > 0)');
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT check_ticket_inventory_balance CHECK (quantity_sold + quantity_reserved <= quantity_total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};

