<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

        protected $fillable = [
        'title',
        'description',
        'original_name',
        'path',
        'mime_type',
        'size',
        'status',
        'uploaded_by',
        'ai_document_id',
        'organization_id',
        'department_id',
        'extracted_text',
    ];


  
        public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }

        public function department()
        {
            return $this->belongsTo(Department::class,'department_id');
        }

}
