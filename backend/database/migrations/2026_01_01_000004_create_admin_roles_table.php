<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['admin_id', 'role_id']);
            $table->timestampTz('assigned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
    }
};

