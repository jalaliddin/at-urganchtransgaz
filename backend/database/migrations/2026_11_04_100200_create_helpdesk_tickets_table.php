<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('location', 150)->nullable();
            $table->foreignId('category_id')->constrained('ticket_categories')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->string('priority', 20);
            $table->string('status', 20);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedInteger('sla_response_minutes');
            $table->unsignedInteger('sla_resolution_minutes');
            $table->timestamp('response_due_at');
            $table->timestamp('resolution_due_at');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('response_breached_at')->nullable();
            $table->timestamp('resolution_breached_at')->nullable();
            $table->timestamp('due_soon_notified_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_minutes')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('resolution')->nullable();
            $table->text('reject_reason')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('rating_comment', 1000)->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->unsignedInteger('reopened_count')->default(0);
            $table->unsignedInteger('time_spent_minutes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['requester_user_id', 'status']);
            $table->index(['status', 'resolution_due_at']);
            $table->index('created_at');
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('ticket_comments')->nullOnDelete();
            $table->foreignId('stored_file_id')->unique()->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('ticket_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('linked_ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ticket_id', 'linked_ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_links');
        Schema::dropIfExists('ticket_histories');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('helpdesk_tickets');
    }
};
