<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_archived']);
        });

        Schema::create('task_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->string('name', 100);
            // open | done | cancelled: decides "finished" and "overdue".
            $table->string('category', 20);
            $table->unsignedInteger('position');
            $table->unsignedInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 20)->default('blue');
            $table->timestamps();

            $table->unique(['board_id', 'name']);
        });

        Schema::create('task_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('task_columns')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->json('label_ids')->nullable();
            $table->string('frequency', 10);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->unsignedSmallInteger('due_in_days')->nullable();
            $table->date('next_run_on');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_on']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('board_id')->constrained('task_boards')->restrictOnDelete();
            $table->foreignId('column_id')->constrained('task_columns')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('project', 150)->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('task_recurrences')->nullOnDelete();
            $table->date('occurrence_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['column_id', 'position']);
            $table->index(['assignee_id', 'completed_at']);
            $table->index(['organization_id', 'due_date']);
            // One task per recurrence and occurrence date.
            $table->unique(['recurrence_id', 'occurrence_date']);
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'user_id']);
        });

        Schema::create('task_label_task', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->primary(['task_id', 'label_id']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position');
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('task_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'id']);
        });

        // Reminder once per task, kind and due date.
        Schema::create('task_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('due_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['task_id', 'type', 'due_date']);
        });
    }

    public function down(): void
    {
        foreach (['task_alerts', 'task_histories', 'task_attachments', 'task_comments', 'task_checklist_items', 'task_label_task',
            'task_watchers', 'tasks', 'task_recurrences', 'task_labels', 'task_columns', 'task_boards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
