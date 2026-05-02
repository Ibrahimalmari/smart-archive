<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentApprovalLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'action',
        'from_status',
        'to_status',
        'acted_by',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
