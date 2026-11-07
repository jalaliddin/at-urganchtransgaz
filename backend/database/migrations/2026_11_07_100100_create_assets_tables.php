<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('inventory_number', 50)->unique();
            $table->string('name', 200);
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('asset_models')->nullOnDelete();
            $table->string('serial_number', 100)->nullable()->index();
            $table->string('hostname', 100)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('mac_address', 17)->nullable()->index();
            $table->string('vlan', 50)->nullable();
            $table->string('subnet', 50)->nullable();
            $table->string('status', 20);
            $table->string('previous_status', 20)->nullable();
            $table->foreignId('holder_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_until')->nullable();
            $table->date('warranty_notified_for')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->string('supplier', 200)->nullable();
            $table->text('specs')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('transfer_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index('warranty_until');
            $table->index(['holder_employee_id', 'status']);
        });

        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'id']);
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_note', 500)->nullable();

            $table->index(['asset_id', 'returned_at']);
            $table->index(['employee_id', 'returned_at']);
        });

        Schema::create('asset_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['asset_id', 'related_asset_id', 'type']);
        });

        Schema::create('asset_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stored_file_id')->unique()->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('vendor', 200)->nullable();
            $table->text('description');
            $table->text('result')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('reason');
            $table->string('act_number', 100)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
        });

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('from_organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('to_organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->foreignId('act_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['from_organization_id', 'status']);
            $table->index(['to_organization_id', 'status']);
        });

        Schema::create('asset_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('asset_transfers')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('condition_note', 500)->nullable();

            $table->unique(['transfer_id', 'asset_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('transfer_id')->references('id')->on('asset_transfers')->nullOnDelete();
        });

        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->boolean('include_descendants')->default(true);
            $table->string('name', 200);
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('unexpected_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('inventory_number', 50);
            $table->boolean('expected');
            $table->timestamp('found_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'inventory_number']);
            $table->index(['session_id', 'expected', 'found_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_sessions');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
        });
        Schema::dropIfExists('asset_transfer_items');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_attachments');
        Schema::dropIfExists('asset_relations');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('asset_events');
        Schema::dropIfExists('assets');
    }
};
