<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name_ar', 150);
            $table->text('description_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('license_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installation_id')->constrained('installations')->restrictOnDelete();
            $table->uuid('license_uuid')->unique();
            $table->unsignedBigInteger('sequence');
            $table->string('key_id', 100);
            $table->string('edition', 100);
            $table->json('features');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->char('document_sha256', 64)->unique();
            $table->longText('signed_document');
            $table->foreignId('issued_by')->nullable()->constrained('vendor_users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at');
            $table->unique(['installation_id', 'sequence']);
            $table->index(['installation_id', 'created_at']);
        });

        foreach ([
            ['system.onboarding', 'تهيئة النظام', 'السماح بإعداد النظام لأول مرة'],
            ['mobile.remote_access', 'الربط بالهاتف', 'السماح بالوصول الآمن من تطبيق الهاتف'],
        ] as [$key, $name, $description]) {
            DB::table('feature_definitions')->insert([
                'key' => $key,
                'name_ar' => $name,
                'description_ar' => $description,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_revisions');
        Schema::dropIfExists('feature_definitions');
    }
};
