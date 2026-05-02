<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('embedding_text_hash', 64)->nullable()->after('archive_reason');
            $table->string('embedding_model', 100)->nullable()->after('embedding_text_hash');
            $table->unsignedInteger('embedding_dimensions')->nullable()->after('embedding_model');
            $table->json('embedding_vector')->nullable()->after('embedding_dimensions');
            $table->timestamp('embedding_indexed_at')->nullable()->after('embedding_vector');
            $table->index('embedding_model');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['embedding_model']);
            $table->dropColumn([
                'embedding_text_hash',
                'embedding_model',
                'embedding_dimensions',
                'embedding_vector',
                'embedding_indexed_at',
            ]);
        });
    }
};
