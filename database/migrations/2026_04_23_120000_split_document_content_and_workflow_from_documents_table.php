<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_contents')) {
            Schema::create('document_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
                $table->longText('ocr_text')->nullable();
                $table->json('ocr_metadata')->nullable();
                $table->string('embedding_text_hash', 64)->nullable();
                $table->string('embedding_model', 100)->nullable();
                $table->unsignedInteger('embedding_dimensions')->nullable();
                $table->json('embedding_vector')->nullable();
                $table->timestamp('embedding_indexed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('document_workflows')) {
            Schema::create('document_workflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('status', 50)->default(Document::STATUS_PENDING);
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('archived_at')->nullable();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('approval_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('archive_reason')->nullable();
                $table->timestamps();
            });
        }

        $this->backfillDocumentContents();
        $this->backfillDocumentWorkflows();

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('documents', 'embedding_model')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->dropIndex(['embedding_model']);
                });
            } catch (\Throwable) {
                // The index may not exist in some test/database states.
            }
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('documents', 'extracted_text') ? 'extracted_text' : null,
            Schema::hasColumn('documents', 'embedding_text_hash') ? 'embedding_text_hash' : null,
            Schema::hasColumn('documents', 'embedding_model') ? 'embedding_model' : null,
            Schema::hasColumn('documents', 'embedding_dimensions') ? 'embedding_dimensions' : null,
            Schema::hasColumn('documents', 'embedding_vector') ? 'embedding_vector' : null,
            Schema::hasColumn('documents', 'embedding_indexed_at') ? 'embedding_indexed_at' : null,
            Schema::hasColumn('documents', 'status') ? 'status' : null,
            Schema::hasColumn('documents', 'submitted_at') ? 'submitted_at' : null,
            Schema::hasColumn('documents', 'submitted_by') ? 'submitted_by' : null,
            Schema::hasColumn('documents', 'reviewed_at') ? 'reviewed_at' : null,
            Schema::hasColumn('documents', 'reviewed_by') ? 'reviewed_by' : null,
            Schema::hasColumn('documents', 'approved_at') ? 'approved_at' : null,
            Schema::hasColumn('documents', 'approved_by') ? 'approved_by' : null,
            Schema::hasColumn('documents', 'rejected_at') ? 'rejected_at' : null,
            Schema::hasColumn('documents', 'rejected_by') ? 'rejected_by' : null,
            Schema::hasColumn('documents', 'archived_at') ? 'archived_at' : null,
            Schema::hasColumn('documents', 'archived_by') ? 'archived_by' : null,
            Schema::hasColumn('documents', 'approval_notes') ? 'approval_notes' : null,
            Schema::hasColumn('documents', 'rejection_reason') ? 'rejection_reason' : null,
            Schema::hasColumn('documents', 'archive_reason') ? 'archive_reason' : null,
        ]));

        if ($columns !== []) {
            $driver = Schema::getConnection()->getDriverName();
            $foreignKeysWereDisabled = false;

            if ($driver === 'sqlite') {
                Schema::disableForeignKeyConstraints();
                $foreignKeysWereDisabled = true;
            }

            Schema::table('documents', function (Blueprint $table) use ($columns, $driver) {
                if ($driver !== 'sqlite') {
                    foreach (['submitted_by', 'reviewed_by', 'approved_by', 'rejected_by', 'archived_by'] as $foreignKeyColumn) {
                        if (in_array($foreignKeyColumn, $columns, true)) {
                            $table->dropForeign([$foreignKeyColumn]);
                        }
                    }
                }

                $table->dropColumn($columns);
            });

            if ($foreignKeysWereDisabled) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'status')) {
                $table->string('status', 50)->default(Document::STATUS_PENDING)->after('size');
            }

            if (!Schema::hasColumn('documents', 'extracted_text')) {
                $table->longText('extracted_text')->nullable()->after('description');
            }

            if (!Schema::hasColumn('documents', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('documents', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_by');
            }

            if (!Schema::hasColumn('documents', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('documents', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_by');
            }

            if (!Schema::hasColumn('documents', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('documents', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_by');
            }

            if (!Schema::hasColumn('documents', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('documents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('rejected_by');
            }

            if (!Schema::hasColumn('documents', 'archived_by')) {
                $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('documents', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('archived_by');
            }

            if (!Schema::hasColumn('documents', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_notes');
            }

            if (!Schema::hasColumn('documents', 'archive_reason')) {
                $table->text('archive_reason')->nullable()->after('rejection_reason');
            }

            if (!Schema::hasColumn('documents', 'embedding_text_hash')) {
                $table->string('embedding_text_hash', 64)->nullable()->after('archive_reason');
            }

            if (!Schema::hasColumn('documents', 'embedding_model')) {
                $table->string('embedding_model', 100)->nullable()->after('embedding_text_hash');
            }

            if (!Schema::hasColumn('documents', 'embedding_dimensions')) {
                $table->unsignedInteger('embedding_dimensions')->nullable()->after('embedding_model');
            }

            if (!Schema::hasColumn('documents', 'embedding_vector')) {
                $table->json('embedding_vector')->nullable()->after('embedding_dimensions');
            }

            if (!Schema::hasColumn('documents', 'embedding_indexed_at')) {
                $table->timestamp('embedding_indexed_at')->nullable()->after('embedding_vector');
            }

            $table->index('embedding_model');
        });

        $this->restoreDocumentContentsToDocuments();
        $this->restoreDocumentWorkflowsToDocuments();

        Schema::dropIfExists('document_contents');
        Schema::dropIfExists('document_workflows');
    }

    private function backfillDocumentContents(): void
    {
        $contentColumns = [
            'extracted_text',
            'embedding_text_hash',
            'embedding_model',
            'embedding_dimensions',
            'embedding_vector',
            'embedding_indexed_at',
        ];

        if (!$this->hasAllDocumentColumns($contentColumns)) {
            return;
        }

        DB::table('documents')
            ->select(['id', ...$contentColumns, 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(100, function ($documents): void {
                foreach ($documents as $document) {
                    $hasContent = $document->extracted_text !== null
                        || $document->embedding_text_hash !== null
                        || $document->embedding_model !== null
                        || $document->embedding_dimensions !== null
                        || $document->embedding_vector !== null
                        || $document->embedding_indexed_at !== null;

                    if (!$hasContent) {
                        continue;
                    }

                    DB::table('document_contents')->updateOrInsert(
                        ['document_id' => $document->id],
                        [
                            'ocr_text' => $document->extracted_text,
                            'ocr_metadata' => null,
                            'embedding_text_hash' => $document->embedding_text_hash,
                            'embedding_model' => $document->embedding_model,
                            'embedding_dimensions' => $document->embedding_dimensions,
                            'embedding_vector' => $document->embedding_vector,
                            'embedding_indexed_at' => $document->embedding_indexed_at,
                            'created_at' => $document->created_at,
                            'updated_at' => $document->updated_at,
                        ]
                    );
                }
            });
    }

    private function backfillDocumentWorkflows(): void
    {
        $workflowColumns = [
            'status',
            'submitted_at',
            'submitted_by',
            'reviewed_at',
            'reviewed_by',
            'approved_at',
            'approved_by',
            'rejected_at',
            'rejected_by',
            'archived_at',
            'archived_by',
            'approval_notes',
            'rejection_reason',
            'archive_reason',
        ];

        if (!$this->hasAllDocumentColumns($workflowColumns)) {
            return;
        }

        DB::table('documents')
            ->select(['id', ...$workflowColumns, 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(100, function ($documents): void {
                foreach ($documents as $document) {
                    DB::table('document_workflows')->updateOrInsert(
                        ['document_id' => $document->id],
                        [
                            'status' => $document->status ?: Document::STATUS_PENDING,
                            'submitted_at' => $document->submitted_at,
                            'submitted_by' => $document->submitted_by,
                            'reviewed_at' => $document->reviewed_at,
                            'reviewed_by' => $document->reviewed_by,
                            'approved_at' => $document->approved_at,
                            'approved_by' => $document->approved_by,
                            'rejected_at' => $document->rejected_at,
                            'rejected_by' => $document->rejected_by,
                            'archived_at' => $document->archived_at,
                            'archived_by' => $document->archived_by,
                            'approval_notes' => $document->approval_notes,
                            'rejection_reason' => $document->rejection_reason,
                            'archive_reason' => $document->archive_reason,
                            'created_at' => $document->created_at,
                            'updated_at' => $document->updated_at,
                        ]
                    );
                }
            });
    }

    private function restoreDocumentContentsToDocuments(): void
    {
        if (!Schema::hasTable('document_contents')) {
            return;
        }

        DB::table('document_contents')
            ->orderBy('document_id')
            ->chunk(100, function ($contents): void {
                foreach ($contents as $content) {
                    DB::table('documents')
                        ->where('id', $content->document_id)
                        ->update([
                            'extracted_text' => $content->ocr_text,
                            'embedding_text_hash' => $content->embedding_text_hash,
                            'embedding_model' => $content->embedding_model,
                            'embedding_dimensions' => $content->embedding_dimensions,
                            'embedding_vector' => $content->embedding_vector,
                            'embedding_indexed_at' => $content->embedding_indexed_at,
                        ]);
                }
            });
    }

    private function restoreDocumentWorkflowsToDocuments(): void
    {
        if (!Schema::hasTable('document_workflows')) {
            return;
        }

        DB::table('document_workflows')
            ->orderBy('document_id')
            ->chunk(100, function ($workflows): void {
                foreach ($workflows as $workflow) {
                    DB::table('documents')
                        ->where('id', $workflow->document_id)
                        ->update([
                            'status' => $workflow->status,
                            'submitted_at' => $workflow->submitted_at,
                            'submitted_by' => $workflow->submitted_by,
                            'reviewed_at' => $workflow->reviewed_at,
                            'reviewed_by' => $workflow->reviewed_by,
                            'approved_at' => $workflow->approved_at,
                            'approved_by' => $workflow->approved_by,
                            'rejected_at' => $workflow->rejected_at,
                            'rejected_by' => $workflow->rejected_by,
                            'archived_at' => $workflow->archived_at,
                            'archived_by' => $workflow->archived_by,
                            'approval_notes' => $workflow->approval_notes,
                            'rejection_reason' => $workflow->rejection_reason,
                            'archive_reason' => $workflow->archive_reason,
                        ]);
                }
            });
    }

    /**
     * @param array<int, string> $columns
     */
    private function hasAllDocumentColumns(array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn('documents', $column)) {
                return false;
            }
        }

        return true;
    }
};
