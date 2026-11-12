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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('provider', 30)->default('paystack');
            $table->string('provider_transaction_id', 100)->nullable()->unique();
            $table->string('channel', 50)->nullable();
            $table->string('mobile_money_provider', 30)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->char('currency', 3)->default('GHS');
            $table->string('status', 30)->default('initiated')->index();
            $table->jsonb('provider_response_sanitized')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->index(['order_id', 'status', 'reference']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT check_payment_amounts CHECK (amount_minor >= 0 AND fees_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

