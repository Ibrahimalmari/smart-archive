<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'title',
        'description',
        'original_name',
        'path',
        'mime_type',
        'size',
        'document_type',
        'classification_confidence',
        'classification_source',
        'uploaded_by',
        'ai_document_id',
        'organization_id',
        'department_id',
    ];

    protected $casts = [
        'classification_confidence' => 'float',
    ];

    protected $with = [
        'workflow',
    ];

    protected $hidden = [
        'workflow',
        'content',
    ];

    protected $appends = [
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

    public static function allStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_ARCHIVED,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function content()
    {
        return $this->hasOne(DocumentContent::class);
    }

    public function workflow()
    {
        return $this->hasOne(DocumentWorkflow::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(DocumentApprovalLog::class)->orderByDesc('id');
    }

    public function getStatusAttribute(): string
    {
        return (string) ($this->workflow?->status ?? self::STATUS_PENDING);
    }

    public function getSubmittedAtAttribute(): mixed
    {
        return $this->workflow?->submitted_at;
    }

    public function getSubmittedByAttribute(): ?int
    {
        return $this->workflow?->submitted_by;
    }

    public function getReviewedAtAttribute(): mixed
    {
        return $this->workflow?->reviewed_at;
    }

    public function getReviewedByAttribute(): ?int
    {
        return $this->workflow?->reviewed_by;
    }

    public function getApprovedAtAttribute(): mixed
    {
        return $this->workflow?->approved_at;
    }

    public function getApprovedByAttribute(): ?int
    {
        return $this->workflow?->approved_by;
    }

    public function getRejectedAtAttribute(): mixed
    {
        return $this->workflow?->rejected_at;
    }

    public function getRejectedByAttribute(): ?int
    {
        return $this->workflow?->rejected_by;
    }

    public function getArchivedAtAttribute(): mixed
    {
        return $this->workflow?->archived_at;
    }

    public function getArchivedByAttribute(): ?int
    {
        return $this->workflow?->archived_by;
    }

    public function getApprovalNotesAttribute(): ?string
    {
        return $this->workflow?->approval_notes;
    }

    public function getRejectionReasonAttribute(): ?string
    {
        return $this->workflow?->rejection_reason;
    }

    public function getArchiveReasonAttribute(): ?string
    {
        return $this->workflow?->archive_reason;
    }
}
