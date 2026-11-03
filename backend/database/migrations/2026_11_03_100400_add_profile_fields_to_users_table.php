<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->foreignId('organization_id')->nullable()->after('email')
                ->comment('Home organization; NULL only for system accounts')
                ->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->unique()->after('organization_id')
                ->constrained()->nullOnDelete();
            $table->string('locale', 5)->default('uz')->after('password');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username', 'locale', 'is_active', 'must_change_password', 'password_changed_at',
                'last_login_at', 'last_login_ip', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'deleted_at',
            ]);
        });
    }
};
