<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. No foreign keys: audit rows must outlive the records they describe.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50);
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_label')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->char('request_id', 36)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
