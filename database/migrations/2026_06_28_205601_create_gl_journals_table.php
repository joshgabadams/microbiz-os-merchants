<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_journals', function (Blueprint $table) {

            $table->id();

            $table->foreignId('gl_account_id')
                ->constrained();

            $table->enum('entry_type', [
                'DEBIT',
                'CREDIT'
            ]);

            $table->decimal('amount', 24, 2);

            $table->string('reference');

            $table->string('source_type');

            $table->unsignedBigInteger('source_id');

            $table->string('currency', 3)
                ->default('NGN');

            $table->text('narration')
                ->nullable();

            $table->timestamp('posted_at');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_journals');
    }
};