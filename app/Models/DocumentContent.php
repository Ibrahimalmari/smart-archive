<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'ocr_text',
        'ocr_metadata',
        'embedding_text_hash',
        'embedding_model',
        'embedding_dimensions',
        'embedding_vector',
        'embedding_indexed_at',
    ];

    protected $casts = [
        'ocr_metadata' => 'array',
        'embedding_dimensions' => 'integer',
        'embedding_vector' => 'array',
        'embedding_indexed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
