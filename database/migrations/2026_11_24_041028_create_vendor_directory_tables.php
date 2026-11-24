<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counties', function (Blueprint $table) {
            $table->id();
            $table->char('fips', 5)->unique();
            $table->char('state', 2)->index();
            $table->string('name', 100);
            $table->unique(['state', 'name']);
        });
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
        });
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 180);
            $table->string('name_key', 180)->unique();
            $table->string('primary_email', 254)->unique();
            $table->string('country', 100)->default('United States');
            $table->text('address')->nullable();
            $table->string('preferred_communication', 10)->default('email');
            $table->json('working_days');
            $table->time('working_start')->nullable();
            $table->time('working_end')->nullable();
            $table->string('time_zone', 100);
            $table->string('availability', 20)->default('available')->index();
            $table->decimal('standard_charge', 10, 2)->nullable();
            $table->decimal('travel_charge', 10, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('payment_terms', 255)->nullable();
            $table->string('priority', 10)->default('normal');
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_demo')->default(false);
            $table->text('remarks')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
        });
        Schema::create('county_vendor', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('county_id')->constrained()->restrictOnDelete();
            $table->primary(['vendor_id', 'county_id']);
            $table->index('county_id');
        });
        Schema::create('document_type_vendor', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->primary(['vendor_id', 'document_type_id']);
            $table->index('document_type_id');
        });
        Schema::create('vendor_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->json('headers');
            $table->json('mapping')->nullable();
            $table->string('status', 20)->default('uploaded');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vendor_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->json('data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['vendor_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        foreach (['vendor_import_rows', 'vendor_imports', 'document_type_vendor', 'county_vendor', 'vendor_contacts', 'vendors', 'document_types', 'counties'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
