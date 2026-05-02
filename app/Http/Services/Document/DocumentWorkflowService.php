<?php

namespace App\Http\Services\Document;

use App\Models\Document;
use App\Models\DocumentApprovalLog;
use App\Models\DocumentWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return [
            'user',
            'organization',
            'department',
            'workflow.submittedBy:id,name,email,role',
            'workflow.reviewedBy:id,name,email,role',
            'workflow.approvedBy:id,name,email,role',
            'workflow.rejectedBy:id,name,email,role',
            'workflow.archivedBy:id,name,email,role',
        ];
    }

    public function submit(Document $document, User $actor, ?string $notes = null): Document
    {
        $this->assertAllowedStatus($document, [
            Document::STATUS_PENDING,
            Document::STATUS_REJECTED,
        ], 'submit');

        $fromStatus = (string) $document->status;

        DB::transaction(function () use ($document, $actor, $notes, $fromStatus): void {
            $workflow = $this->workflowRecord($document);

            $workflow->fill([
                'status' => Document::STATUS_UNDER_REVIEW,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'archived_at' => null,
                'archived_by' => null,
                'approval_notes' => null,
                'rejection_reason' => null,
                'archive_reason' => null,
            ])->save();

            $this->log($document, 'submit', $fromStatus, $workflow->status, $actor->id, $notes);
        });

        return $document->fresh($this->relations()) ?? $document;
    }

    public function approve(Document $document, User $actor, ?string $notes = null): Document
    {
        $this->assertAllowedStatus($document, [Document::STATUS_UNDER_REVIEW], 'approve');

        $fromStatus = (string) $document->status;

        DB::transaction(function () use ($document, $actor, $notes, $fromStatus): void {
            $workflow = $this->workflowRecord($document);

            $workflow->fill([
                'status' => Document::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => $actor->id,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'approval_notes' => $notes,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ])->save();

            $this->log($document, 'approve', $fromStatus, $workflow->status, $actor->id, $notes);
        });

        return $document->fresh($this->relations()) ?? $document;
    }

    public function reject(Document $document, User $actor, string $reason): Document
    {
        $this->assertAllowedStatus($document, [Document::STATUS_UNDER_REVIEW], 'reject');

        $fromStatus = (string) $document->status;

        DB::transaction(function () use ($document, $actor, $reason, $fromStatus): void {
            $workflow = $this->workflowRecord($document);

            $workflow->fill([
                'status' => Document::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => $actor->id,
                'rejected_at' => now(),
                'rejected_by' => $actor->id,
                'rejection_reason' => $reason,
                'approved_at' => null,
                'approved_by' => null,
                'approval_notes' => null,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ])->save();

            $this->log($document, 'reject', $fromStatus, $workflow->status, $actor->id, $reason);
        });

        return $document->fresh($this->relations()) ?? $document;
    }

    public function archive(Document $document, User $actor, ?string $reason = null): Document
    {
        $this->assertAllowedStatus($document, [Document::STATUS_APPROVED], 'archive');

        $fromStatus = (string) $document->status;

        DB::transaction(function () use ($document, $actor, $reason, $fromStatus): void {
            $workflow = $this->workflowRecord($document);

            $workflow->fill([
                'status' => Document::STATUS_ARCHIVED,
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
            ])->save();

            $this->log($document, 'archive', $fromStatus, $workflow->status, $actor->id, $reason);
        });

        return $document->fresh($this->relations()) ?? $document;
    }

    public function reopen(Document $document, User $actor, ?string $notes = null): Document
    {
        $this->assertAllowedStatus($document, [
            Document::STATUS_APPROVED,
            Document::STATUS_ARCHIVED,
        ], 'reopen');

        $fromStatus = (string) $document->status;

        DB::transaction(function () use ($document, $actor, $notes, $fromStatus): void {
            $workflow = $this->workflowRecord($document);

            $workflow->fill([
                'status' => Document::STATUS_UNDER_REVIEW,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'archived_at' => null,
                'archived_by' => null,
                'approval_notes' => null,
                'rejection_reason' => null,
                'archive_reason' => null,
            ])->save();

            $this->log($document, 'reopen', $fromStatus, $workflow->status, $actor->id, $notes);
        });

        return $document->fresh($this->relations()) ?? $document;
    }

    /**
     * @param array<int, string> $allowedStatuses
     */
    private function assertAllowedStatus(Document $document, array $allowedStatuses, string $action): void
    {
        if (!in_array($document->status, $allowedStatuses, true)) {
            throw new DocumentWorkflowTransitionException(
                action: $action,
                currentStatus: (string) $document->status,
                allowedStatuses: $allowedStatuses,
            );
        }
    }

    private function log(
        Document $document,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actedBy,
        ?string $notes = null,
        ?array $meta = null
    ): void {
        DocumentApprovalLog::create([
            'document_id' => $document->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'acted_by' => $actedBy,
            'notes' => $notes,
            'meta' => $meta,
        ]);
    }

    private function workflowRecord(Document $document): DocumentWorkflow
    {
        $workflow = $document->relationLoaded('workflow')
            ? $document->getRelation('workflow')
            : $document->workflow()->first();

        if ($workflow === null) {
            $workflow = $document->workflow()->create([
                'status' => Document::STATUS_PENDING,
            ]);
        }

        $document->setRelation('workflow', $workflow);

        return $workflow;
    }
}
