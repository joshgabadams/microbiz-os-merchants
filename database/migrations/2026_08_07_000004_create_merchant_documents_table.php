<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant Management Services Blueprint 8.3. storage_path is nullable
 * here even though the Blueprint has it required -- there's no file
 * upload UI yet, only document type/number capture. Tighten this once
 * actual file upload exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->string('storage_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('verification_status')->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_documents');
    }
};
