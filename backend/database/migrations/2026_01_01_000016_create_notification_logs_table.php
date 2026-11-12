<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('channel', 30); // email, sms, etc.
            $table->string('recipient_masked', 255)->nullable();
            $table->string('template_key', 100);
            $table->string('status', 30)->default('queued')->index();
            $table->string('provider_message_id', 150)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampsTz();

            $table->index(['status', 'channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

