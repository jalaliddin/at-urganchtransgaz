<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 warehouse: catalog, warehouses, lots (stock_balances), posted documents,
 * movement journal, stock counts and alerts. Balances change only in StockService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->string('name_ru', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->string('name_ru', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('material_categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->decimal('min_quantity', 18, 3)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        // A lot: one row per warehouse, material and batch ('' when the material has no batches).
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('batch', 100)->default('');
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'material_id', 'batch']);
            $table->index(['material_id', 'warehouse_id']);
        });
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_quantity_non_negative CHECK (quantity >= 0)');

        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('type', 20);
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('target_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->date('document_date');
            $table->string('supplier')->nullable();
            $table->string('supplier_document', 100)->nullable();
            $table->foreignId('recipient_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('recipient_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('related_document_id')->nullable()->constrained('stock_documents')->restrictOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'document_date']);
            $table->index(['warehouse_id', 'document_date']);
        });

        // Lines are what the user entered; quantity is signed only for adjustments.
        Schema::create('stock_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('stock_documents')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('batch', 100)->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('note')->nullable();
        });

        // Journal: one row per lot change, with the lot balance after it.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained('stock_documents')->restrictOnDelete();
            $table->foreignId('line_id')->nullable()->constrained('stock_document_lines')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('balance_id')->constrained('stock_balances')->restrictOnDelete();
            $table->string('batch', 100)->default('');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('balance_after', 18, 3);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['warehouse_id', 'material_id', 'id']);
            $table->index(['material_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('status', 20);
            // Set to the warehouse id while open, NULL afterwards: one open count per warehouse.
            $table->unsignedBigInteger('open_key')->nullable()->unique();
            $table->text('note')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('adjustment_document_id')->nullable()->constrained('stock_documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('batch', 100)->default('');
            $table->date('expires_at')->nullable();
            $table->decimal('expected_quantity', 18, 3)->default(0);
            $table->decimal('counted_quantity', 18, 3)->nullable();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();

            $table->unique(['count_id', 'material_id', 'batch']);
        });

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreignId('stock_count_id')->nullable()->after('related_document_id')->constrained('stock_counts')->nullOnDelete();
        });

        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('batch', 100)->default('');
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            // Unique while the episode is open (NULL once resolved): one open alert per subject.
            $table->string('open_key', 191)->nullable()->unique();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['organization_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_count_id');
        });
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_document_lines');
        Schema::dropIfExists('stock_documents');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('units');
    }
};
