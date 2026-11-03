<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->string('name_ru', 150)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('priority', 20);
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->cascadeOnDelete();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // NULL category = priority-wide default; uniqueness of that pair is enforced in code.
            $table->unique(['priority', 'category_id']);
        });

        Schema::create('ticket_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_counters');
        Schema::dropIfExists('ticket_sla_policies');
        Schema::dropIfExists('ticket_categories');
    }
};
