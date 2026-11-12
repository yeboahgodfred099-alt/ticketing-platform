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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->string('ticket_name_snapshot', 150);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('GHS');
            $table->timestampsTz();

            $table->index(['order_id', 'ticket_type_id']);
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT check_order_item_values CHECK (quantity > 0 AND unit_price_minor >= 0 AND total_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

