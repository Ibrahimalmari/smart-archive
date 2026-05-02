<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable()->after('submitted_by');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('reviewed_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();

            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();

            $table->timestamp('archived_at')->nullable()->after('rejected_by');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();

            $table->text('approval_notes')->nullable()->after('archived_by');
            $table->text('rejection_reason')->nullable()->after('approval_notes');
            $table->text('archive_reason')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('archived_by');

            $table->dropColumn([
                'submitted_at',
                'reviewed_at',
                'approved_at',
                'rejected_at',
                'archived_at',
                'approval_notes',
                'rejection_reason',
                'archive_reason',
            ]);
        });
    }
};
