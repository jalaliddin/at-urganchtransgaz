<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1:1 printer profile of an asset in the `printer` category (created on first write).
        Schema::create('printers', function (Blueprint $table) {
            $table->foreignId('asset_id')->primary()->constrained('assets')->cascadeOnDelete();
            $table->string('connection_type', 20)->nullable();
            $table->boolean('is_color')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Global catalog; stock of a cartridge model is the stock of its material.
        Schema::create('cartridge_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained('materials')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('part_number', 100)->nullable()->unique();
            $table->string('kind', 20);
            $table->string('color', 20);
            $table->unsignedInteger('page_yield')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('printer_cartridge_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_model_id')->constrained('asset_models')->cascadeOnDelete();
            $table->foreignId('cartridge_model_id')->constrained('cartridge_models')->cascadeOnDelete();

            $table->unique(['asset_model_id', 'cartridge_model_id'], 'printer_cartridge_unique');
        });

        // §8 cartridge_movements: which cartridge went into which printer. Stock moves stay in stock_movements.
        Schema::create('cartridge_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('cartridge_model_id')->constrained('cartridge_models')->restrictOnDelete();
            $table->date('replaced_at');
            $table->string('source', 20);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_document_id')->nullable()->constrained('stock_documents')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('return_document_id')->nullable()->constrained('stock_documents')->restrictOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'replaced_at']);
            $table->index(['organization_id', 'replaced_at']);
            $table->index(['cartridge_model_id', 'replaced_at']);
        });

        Schema::create('printer_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->date('read_at');
            $table->unsignedBigInteger('total');
            $table->boolean('is_reset')->default(false);
            $table->string('source', 20);
            $table->foreignId('replacement_id')->nullable()->constrained('cartridge_replacements')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'read_at', 'id']);
            $table->index(['organization_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_counters');
        Schema::dropIfExists('cartridge_replacements');
        Schema::dropIfExists('printer_cartridge_compatibilities');
        Schema::dropIfExists('cartridge_models');
        Schema::dropIfExists('printers');
    }
};
