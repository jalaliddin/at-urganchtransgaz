<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mobile operators, internet providers and other IT service vendors in one registry.
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('tin', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_mobile_operator')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sim_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('service_providers')->restrictOnDelete();
            $table->string('phone_number', 12)->index();
            // Equals phone_number while the card is not cancelled: one live card per number.
            $table->string('phone_key', 12)->nullable()->unique();
            $table->text('iccid')->nullable();
            $table->char('iccid_hash', 64)->nullable()->unique();
            $table->char('iccid_last4', 4)->nullable();
            $table->string('tariff', 150)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->decimal('monthly_fee', 15, 2)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('previous_status', 20)->nullable();
            $table->date('allocated_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('sim_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sim_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('assigned_at');
            $table->date('returned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['sim_card_id', 'returned_at']);
            $table->index(['employee_id', 'returned_at']);
        });

        Schema::create('sim_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sim_card_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->foreignId('requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['sim_card_id', 'id']);
        });

        Schema::create('service_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('service_providers')->restrictOnDelete();
            $table->string('number', 100);
            $table->string('subject', 255)->nullable();
            $table->date('signed_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->date('terminated_at')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider_id', 'number']);
            $table->index(['organization_id', 'valid_to']);
        });

        // "services" would clash with the App\Services namespace in code; these are IT/communication services.
        Schema::create('it_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('service_providers')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('service_contracts')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('name', 200);
            $table->string('address', 255)->nullable();
            $table->string('identifier', 100)->nullable();
            $table->string('tariff', 150)->nullable();
            // Amount per billing period.
            $table->decimal('fee', 15, 2)->default(0);
            $table->string('billing_cycle', 10)->default('monthly');
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['provider_id', 'type']);
        });

        Schema::create('service_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('it_services')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->date('period_start');
            $table->date('period_end');
            // "<service>:<period_start>" for regular rows: one per service and period; NULL for extra invoices.
            $table->string('period_key', 40)->nullable()->unique();
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('due_date');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index(['service_id', 'period_start']);
        });

        Schema::create('service_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('service_invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('document_number', 100)->nullable();
            $table->foreignId('file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE service_payments ADD CONSTRAINT service_payments_amount_positive CHECK (amount > 0)');

        // One reminder per object, kind and date (due date or contract end).
        Schema::create('service_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('alertable_type', 50);
            $table->unsignedBigInteger('alertable_id');
            $table->date('alert_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['type', 'alertable_type', 'alertable_id', 'alert_date'], 'service_alerts_once');
        });

        Schema::table('requisition_fulfillments', function (Blueprint $table) {
            $table->foreignId('sim_card_id')->nullable()->after('asset_transfer_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_fulfillments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sim_card_id');
        });
        Schema::dropIfExists('service_alerts');
        Schema::dropIfExists('service_payments');
        Schema::dropIfExists('service_invoices');
        Schema::dropIfExists('it_services');
        Schema::dropIfExists('service_contracts');
        Schema::dropIfExists('sim_histories');
        Schema::dropIfExists('sim_assignments');
        Schema::dropIfExists('sim_cards');
        Schema::dropIfExists('service_providers');
    }
};
