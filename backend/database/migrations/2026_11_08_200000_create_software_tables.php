<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 5: software catalog, licences, installations, policies, antivirus, agents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('normalized_name', 150)->unique();
            $table->string('vendor', 150)->nullable();
            $table->string('category', 30);
            $table->string('license_type', 20);
            $table->boolean('is_antivirus')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('software')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('number', 100)->nullable();
            $table->text('license_key')->nullable();
            $table->unsignedInteger('seats');
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('supplier')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'expires_at']);
        });

        // One row per device and normalised name: a catalog title or an unrecognised raw name.
        Schema::create('software_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('software_id')->nullable()->constrained('software')->restrictOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('vendor', 150)->nullable();
            $table->string('version', 100)->nullable();
            $table->string('source', 10);
            $table->foreignId('license_id')->nullable()->constrained('software_licenses')->nullOnDelete();
            $table->date('installed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'normalized_name']);
            $table->index('software_id');
            $table->index('normalized_name');
        });

        Schema::create('software_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('software')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('antivirus_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('software_id')->nullable()->constrained('software')->nullOnDelete();
            $table->string('product_name', 150)->nullable();
            $table->string('state', 20);
            $table->timestamp('definitions_updated_at')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamp('reported_at');
            $table->string('source', 10);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('device_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('platform', 50)->nullable();
            $table->string('agent_version', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checkin_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Tokens are stored only as SHA-256 hashes; the plain value is shown once.
        Schema::create('agent_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('device_agents')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('prefix', 12);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('agent_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('device_agents')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('status', 10);
            $table->unsignedInteger('software_count')->default(0);
            $table->string('message')->nullable();
            $table->timestamp('received_at');

            $table->index(['agent_id', 'received_at']);
        });

        Schema::create('license_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('software_licenses')->cascadeOnDelete();
            $table->date('expires_at');
            $table->timestamp('notified_at');

            $table->unique(['license_id', 'expires_at']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->timestamp('software_inventoried_at')->nullable()->after('warranty_notified_for');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('software_inventoried_at');
        });
        Schema::dropIfExists('license_alerts');
        Schema::dropIfExists('agent_checkins');
        Schema::dropIfExists('agent_tokens');
        Schema::dropIfExists('device_agents');
        Schema::dropIfExists('antivirus_statuses');
        Schema::dropIfExists('software_policies');
        Schema::dropIfExists('software_installations');
        Schema::dropIfExists('software_licenses');
        Schema::dropIfExists('software');
    }
};
