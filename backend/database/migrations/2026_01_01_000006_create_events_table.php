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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->text('banner_path')->nullable();
            $table->string('venue_name', 255)->nullable();
            $table->text('venue_address')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('sales_starts_at')->nullable();
            $table->timestampTz('sales_ends_at')->nullable();
            $table->string('timezone', 64)->default('Africa/Accra');
            $table->string('status', 30)->default('draft')->index();
            $table->foreignUuid('created_by')->constrained('admins')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'starts_at']);
        });

        DB::statement('ALTER TABLE events ADD CONSTRAINT check_event_dates CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

