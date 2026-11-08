<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 4 requisitions: items, approvals, history, comments, files, fulfilments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30);
            $table->date('needed_by')->nullable();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->foreignId('material_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->restrictOnDelete();
            $table->string('name');
            $table->decimal('quantity', 18, 3);
            $table->decimal('approved_quantity', 18, 3)->nullable();
            $table->decimal('fulfilled_quantity', 18, 3)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('decision', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('requisition_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('comment')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['requisition_id', 'id']);
        });

        Schema::create('requisition_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('requisition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('requisition_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('requisition_items')->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 18, 3);
            $table->foreignId('stock_document_id')->nullable()->constrained('stock_documents')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignId('asset_transfer_id')->nullable()->constrained('asset_transfers')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreignId('requisition_id')->nullable()->after('stock_count_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requisition_id');
        });
        Schema::dropIfExists('requisition_fulfillments');
        Schema::dropIfExists('requisition_attachments');
        Schema::dropIfExists('requisition_comments');
        Schema::dropIfExists('requisition_histories');
        Schema::dropIfExists('requisition_approvals');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
    }
};
