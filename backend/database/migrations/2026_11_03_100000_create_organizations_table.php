<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->string('path', 500)->default('')->index()->comment('Materialized path incl. self, e.g. /1/5/12/');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('type', 32);
            $table->string('name');
            $table->string('short_name', 100)->nullable();
            $table->string('code', 50)->unique();
            $table->string('tin', 9)->nullable()->unique()->comment('STIR/INN');
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('head_name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
