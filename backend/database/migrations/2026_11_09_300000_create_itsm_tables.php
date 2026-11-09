<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Knowledge base (global; visibility by audience and status).
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('title');
            $table->string('type', 20);
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('summary', 500)->nullable();
            $table->mediumText('body');
            $table->string('audience', 10);
            $table->string('status', 20);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->foreignId('source_ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'audience']);
            $table->index('category_id');
        });

        Schema::create('kb_article_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kb_article_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();

            $table->unique(['article_id', 'user_id']);
        });

        // Problem management.
        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('priority', 10);
            $table->string('status', 20);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('root_cause')->nullable();
            $table->text('workaround')->nullable();
            $table->text('resolution')->nullable();
            $table->string('close_reason', 500)->nullable();
            $table->foreignId('kb_article_id')->nullable()->constrained('kb_articles')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('problem_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['problem_id', 'ticket_id']);
            $table->index('ticket_id');
        });

        Schema::create('problem_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->json('payload')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['problem_id', 'id']);
        });

        // Change management.
        Schema::create('changes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->string('category', 20);
            $table->string('risk', 10);
            $table->text('impact')->nullable();
            $table->text('plan')->nullable();
            $table->text('backout_plan')->nullable();
            $table->dateTime('planned_start')->nullable();
            $table->dateTime('planned_end')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->string('status', 20);
            $table->foreignId('implementer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('problem_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->text('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('planned_start');
        });

        Schema::create('change_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->unique(['change_id', 'asset_id']);
            $table->index('asset_id');
        });

        Schema::create('change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->json('payload')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['change_id', 'id']);
        });

        // Preventive maintenance.
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('checklist')->nullable();
            $table->unsignedSmallInteger('interval_count');
            $table->string('interval_unit', 10);
            $table->date('start_on');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('lead_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('maintenance_plan_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('maintenance_plans')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->unique(['plan_id', 'asset_id']);
        });

        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('maintenance_plans')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->string('status', 20);
            // "plan:asset" while planned: at most one open item per plan and asset.
            $table->string('open_key', 40)->nullable()->unique();
            $table->date('completed_on')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 10)->nullable();
            $table->json('checklist')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'asset_id', 'due_on']);
            $table->index(['organization_id', 'status', 'due_on']);
        });

        Schema::create('maintenance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('maintenance_tasks')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['task_id', 'type']);
        });
    }

    public function down(): void
    {
        foreach (['maintenance_alerts', 'maintenance_tasks', 'maintenance_plan_assets', 'maintenance_plans', 'change_histories',
            'change_assets', 'changes', 'problem_histories', 'problem_tickets', 'problems', 'kb_article_votes',
            'kb_article_attachments', 'kb_articles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
