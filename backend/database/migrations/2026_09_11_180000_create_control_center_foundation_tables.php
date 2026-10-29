<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name_ar', 150);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('vendor_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_user_id')->constrained('vendor_users')->restrictOnDelete();
            $table->string('code_hash', 255);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['vendor_user_id', 'used_at']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->string('legal_name', 200);
            $table->string('display_name', 200);
            $table->string('contract_state', 32);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamps();
            $table->index('contract_state');
        });

        Schema::create('customer_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->uuid('installation_uuid')->unique();
            $table->string('site_label', 150);
            $table->string('deployment_mode', 20);
            $table->string('activation_state', 32);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['activation_state', 'deployment_mode']);
            $table->index('customer_id');
        });

        Schema::create('control_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->string('actor_email', 190)->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained('installations')->nullOnDelete();
            $table->string('request_id', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('risk', 20);
            $table->string('reason', 500)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->timestamp('created_at');
            $table->index(['action', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_audit_events');
        Schema::dropIfExists('installations');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('recovery_codes');
        Schema::dropIfExists('vendor_users');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
