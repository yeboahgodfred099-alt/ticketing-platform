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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 50)->unique();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255)->index();
            $table->string('customer_phone', 30)->index();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3)->default('GHS');
            $table->string('status', 30)->default('pending_payment')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('paid_at')->nullable();
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->timestampsTz();

            $table->index(['event_id', 'status', 'created_at']);
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT check_order_amounts CHECK (subtotal_minor >= 0 AND discount_minor >= 0 AND total_amount_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

